<?php
/**
 * Arranque común: carga la configuración, abre la conexión a la base de
 * datos y define las utilidades que usan todas las páginas.
 * Se incluye siempre con:  require_once __DIR__ . '/../includes/arranque.php';
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
//  Configuración
// ---------------------------------------------------------------------
$rutaConfig = __DIR__ . '/config.php';
if (!is_file($rutaConfig)) {
    http_response_code(500);
    exit('Falta includes/config.php. Copia includes/config.ejemplo.php y rellena los datos.');
}

/** @var array $CONFIG */
$CONFIG = require $rutaConfig;

date_default_timezone_set($CONFIG['app']['zona_horaria'] ?? 'Europe/Madrid');

// En producción no se muestran errores al visitante, pero sí se registran.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// ---------------------------------------------------------------------
//  Base de datos
// ---------------------------------------------------------------------
function bd(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    global $CONFIG;
    $c = $CONFIG['bd'];
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $c['host'], $c['nombre']);

    try {
        $pdo = new PDO($dsn, $c['usuario'], $c['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // NOW() de MySQL debe coincidir con la hora local de PHP (si no, las
        // horas guardadas y las comparaciones salen desfasadas).
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    } catch (PDOException $e) {
        error_log('Error de conexión a la BD: ' . $e->getMessage());
        http_response_code(500);
        exit('No se puede conectar con la base de datos. Inténtalo más tarde.');
    }

    return $pdo;
}

// ---------------------------------------------------------------------
//  Utilidades
// ---------------------------------------------------------------------

/** Escapa texto para imprimirlo en HTML sin riesgo de inyección. */
function e(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Devuelve una respuesta JSON y termina la ejecución. */
function json(array $datos, int $codigo = 200): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Atajo para devolver un error en formato JSON. */
function jsonError(string $mensaje, int $codigo = 400): never
{
    json(['ok' => false, 'error' => $mensaje], $codigo);
}

/** Lee el cuerpo JSON de una petición. Devuelve [] si no es válido. */
function cuerpoJson(): array
{
    $crudo = file_get_contents('php://input') ?: '';
    $datos = json_decode($crudo, true);
    return is_array($datos) ? $datos : [];
}

/** Genera el código corto que se le enseña al alumno: 4 números y 1 letra (p. ej. "1234A"). */
function generarCodigo(): string
{
    // Sin O ni I, para no confundirlas con 0 y 1 al leerlas en voz alta.
    $letras = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $numero = random_int(0, 9999);
    $letra  = $letras[random_int(0, strlen($letras) - 1)];
    return sprintf('%04d%s', $numero, $letra);
}

/**
 * Receta de cada producto: qué ingredientes usa y cuántas unidades de cada
 * uno por unidad vendida (p. ej. "Bocadillo de lomo" = 1 Pan de bocadillo +
 * 2 Lomo). Un producto sin receta (p. ej. un donut) no usa esta tabla: su
 * stock es simplemente productos.stock.
 *
 * @return array<int, array<int, array{ingrediente_id:int, cantidad:int}>> indexado por producto_id
 */
function recetasDeProductos(PDO $pdo, array $productoIds): array
{
    $productoIds = array_values(array_unique(array_filter($productoIds)));
    if (!$productoIds) {
        return [];
    }
    $marcadores = implode(',', array_fill(0, count($productoIds), '?'));
    $consulta = $pdo->prepare(
        "SELECT producto_id, ingrediente_id, cantidad FROM producto_ingredientes WHERE producto_id IN ($marcadores)"
    );
    $consulta->execute($productoIds);

    $recetas = [];
    foreach ($consulta->fetchAll() as $fila) {
        $recetas[(int) $fila['producto_id']][] = [
            'ingrediente_id' => (int) $fila['ingrediente_id'],
            'cantidad'       => (int) $fila['cantidad'],
        ];
    }
    return $recetas;
}

/**
 * Descuenta el stock de cada línea de forma atómica, para que dos pedidos
 * simultáneos nunca puedan vender más unidades de las que quedan. Si el
 * producto tiene receta, se descuenta de sus ingredientes (cantidad x
 * unidades pedidas); si no, se descuenta directamente de productos.stock.
 * Cada UPDATE solo tiene efecto si sigue habiendo stock suficiente en ese
 * instante (o si no hay límite, stock IS NULL); el bloqueo de fila de
 * InnoDB en el UPDATE es lo que hace la operación segura entre peticiones
 * concurrentes, sin necesidad de bloqueos explícitos.
 *
 * Debe llamarse dentro de una transacción ya abierta. Devuelve null si todo
 * fue bien, o un mensaje de error (lo primero sin stock suficiente) si hay
 * que abortar y hacer rollback.
 *
 * @param array<int, array{producto_id:int, nombre_producto:string, cantidad:int}> $lineas
 */
function descontarStock(PDO $pdo, array $lineas): ?string
{
    $recetas = recetasDeProductos($pdo, array_column($lineas, 'producto_id'));

    $descontarIngrediente = $pdo->prepare(
        'UPDATE ingredientes SET stock = stock - ? WHERE id = ? AND (stock IS NULL OR stock >= ?)'
    );
    $descontarProducto = $pdo->prepare(
        'UPDATE productos SET stock = stock - ? WHERE id = ? AND (stock IS NULL OR stock >= ?)'
    );

    foreach ($lineas as $linea) {
        $receta = array_key_exists('receta', $linea) ? $linea['receta'] : ($recetas[$linea['producto_id']] ?? []);

        if (!$receta) {
            $descontarProducto->execute([$linea['cantidad'], $linea['producto_id'], $linea['cantidad']]);
            if ($descontarProducto->rowCount() === 0) {
                $consulta = $pdo->prepare('SELECT stock FROM productos WHERE id = ?');
                $consulta->execute([$linea['producto_id']]);
                $stockActual = $consulta->fetchColumn();

                // rowCount() cuenta filas modificadas, no encontradas: con stock
                // NULL (ilimitado) el UPDATE no cambia nada y devuelve 0.
                if ($stockActual === null) {
                    continue;
                }

                return $stockActual === false
                    ? "El producto \"{$linea['nombre_producto']}\" ya no existe."
                    : "Solo quedan {$stockActual} unidades de \"{$linea['nombre_producto']}\". Ajusta la cantidad e inténtalo de nuevo.";
            }
            continue;
        }

        foreach ($receta as $ingrediente) {
            $necesario = $ingrediente['cantidad'] * $linea['cantidad'];
            $descontarIngrediente->execute([$necesario, $ingrediente['ingrediente_id'], $necesario]);

            if ($descontarIngrediente->rowCount() === 0) {
                $consulta = $pdo->prepare('SELECT nombre, stock FROM ingredientes WHERE id = ?');
                $consulta->execute([$ingrediente['ingrediente_id']]);
                $fila = $consulta->fetch();

                if ($fila !== false && $fila['stock'] === null) {
                    continue; // ingrediente ilimitado (ver el caso de productos arriba)
                }

                return $fila === false
                    ? "Un ingrediente de \"{$linea['nombre_producto']}\" ya no existe."
                    : "No queda suficiente \"{$fila['nombre']}\" para preparar \"{$linea['nombre_producto']}\" (quedan {$fila['stock']}). Ajusta la cantidad e inténtalo de nuevo.";
            }
        }
    }

    return null;
}

/** Devuelve al stock las unidades de un pedido (al cancelarlo o borrarlo). */
function reponerStock(PDO $pdo, array $lineas): void
{
    $recetas = recetasDeProductos($pdo, array_column($lineas, 'producto_id'));

    $reponerIngrediente = $pdo->prepare('UPDATE ingredientes SET stock = stock + ? WHERE id = ? AND stock IS NOT NULL');
    $reponerProducto    = $pdo->prepare('UPDATE productos SET stock = stock + ? WHERE id = ? AND stock IS NOT NULL');

    foreach ($lineas as $linea) {
        $receta = array_key_exists('receta', $linea) ? $linea['receta'] : ($recetas[$linea['producto_id']] ?? []);

        if (!$receta) {
            $reponerProducto->execute([$linea['cantidad'], $linea['producto_id']]);
            continue;
        }

        foreach ($receta as $ingrediente) {
            $reponerIngrediente->execute([$ingrediente['cantidad'] * $linea['cantidad'], $ingrediente['ingrediente_id']]);
        }
    }
}

/**
 * Calcula el stock "vendible" de cada producto activo, teniendo en cuenta
 * su receta de ingredientes (el cuello de botella: el ingrediente que
 * menos unidades permite fabricar). Un producto sin receta usa
 * directamente productos.stock. null = sin límite.
 *
 * @return array<int, array<string,mixed>> productos con 'stock' ya resuelto
 *         y una clave adicional 'ingredientes' con el detalle de la receta.
 */
function catalogoConStock(bool $soloActivos = true): array
{
    $pdo = bd();
    $sql = 'SELECT id, nombre, categoria, precio, stock, icono, activo, orden FROM productos'
         . ($soloActivos ? ' WHERE activo = 1' : '')
         . ' ORDER BY orden, id';
    $productos = $pdo->query($sql)->fetchAll();

    $recetaFilas = $pdo->query(
        'SELECT pi.producto_id, pi.ingrediente_id, pi.cantidad,
                i.nombre AS ingrediente_nombre, i.stock AS ingrediente_stock
           FROM producto_ingredientes pi
           JOIN ingredientes i ON i.id = pi.ingrediente_id'
    )->fetchAll();

    $porProducto = [];
    foreach ($recetaFilas as $fila) {
        $porProducto[(int) $fila['producto_id']][] = $fila;
    }

    foreach ($productos as &$producto) {
        $producto['id']     = (int) $producto['id'];
        $producto['precio'] = (float) $producto['precio'];
        $producto['activo'] = (bool) $producto['activo'];
        $producto['orden']  = (int) $producto['orden'];
        $producto['icono']  = $producto['icono'] ?: null;
        // Valor guardado directamente en productos.stock, tal cual, antes de
        // aplicar el límite de la receta (lo necesita el panel de admin para
        // rellenar el campo "Stock" al editar un producto sin ingredientes).
        $producto['stock_propio'] = $producto['stock'] !== null ? (int) $producto['stock'] : null;

        $receta = $porProducto[$producto['id']] ?? [];

        if ($receta) {
            $limite = null;
            foreach ($receta as $r) {
                if ($r['ingrediente_stock'] === null) {
                    continue; // ese ingrediente no tiene límite: no restringe
                }
                $posibles = intdiv((int) $r['ingrediente_stock'], max(1, (int) $r['cantidad']));
                $limite = $limite === null ? $posibles : min($limite, $posibles);
            }
            $producto['stock'] = $limite;
            $producto['ingredientes'] = array_map(static fn($r) => [
                'ingrediente_id'    => (int) $r['ingrediente_id'],
                'nombre'            => $r['ingrediente_nombre'],
                'cantidad'          => (int) $r['cantidad'],
                'stock_ingrediente' => $r['ingrediente_stock'] !== null ? (int) $r['ingrediente_stock'] : null,
            ], $receta);
        } else {
            $producto['stock'] = $producto['stock'] !== null ? (int) $producto['stock'] : null;
            $producto['ingredientes'] = [];
        }
    }
    unset($producto);

    return $productos;
}

/**
 * Lee las líneas de un pedido en el formato que usan descontarStock()/reponerStock().
 * Si la línea guardó la receta que tenía al pedirse (receta_json), se devuelve en
 * 'receta' para devolver/descontar exactamente lo mismo aunque la receta del
 * producto haya cambiado después.
 */
function lineasDelPedido(PDO $pdo, int $pedidoId): array
{
    $consulta = $pdo->prepare(
        'SELECT producto_id, nombre_producto, cantidad, receta_json FROM pedido_lineas WHERE pedido_id = ?'
    );
    $consulta->execute([$pedidoId]);

    return array_map(static function ($f) {
        $linea = [
            'producto_id'     => (int) $f['producto_id'],
            'nombre_producto' => $f['nombre_producto'],
            'cantidad'        => (int) $f['cantidad'],
        ];
        if ($f['receta_json'] !== null) {
            $linea['receta'] = json_decode($f['receta_json'], true) ?: [];
        }
        return $linea;
    }, $consulta->fetchAll());
}

/**
 * Inserta las líneas de un pedido guardando la receta vigente de cada producto,
 * y la añade a $lineas para que descontarStock() use esa misma receta.
 *
 * @param array<int, array<string,mixed>> $lineas
 */
function insertarLineasPedido(PDO $pdo, int $pedidoId, array &$lineas): void
{
    $recetas = recetasDeProductos($pdo, array_column($lineas, 'producto_id'));
    $insertar = $pdo->prepare(
        'INSERT INTO pedido_lineas (pedido_id, producto_id, nombre_producto, precio_unitario, cantidad, receta_json)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ($lineas as &$linea) {
        $linea['receta'] = $recetas[$linea['producto_id']] ?? [];
        $insertar->execute([
            $pedidoId,
            $linea['producto_id'],
            $linea['nombre_producto'],
            $linea['precio_unitario'],
            $linea['cantidad'],
            json_encode($linea['receta']),
        ]);
    }
    unset($linea);
}

/**
 * Llama a la acción del Apps Script (registrar en el Sheet o mandar un
 * email), directamente desde el servidor. Antes esto lo hacía el propio
 * navegador del panel porque InfinityFree bloqueaba las conexiones
 * salientes a script.google.com; el hosting actual sí puede alcanzarlo,
 * así que la contraseña del webhook ya no necesita viajar al navegador.
 *
 * @return array{ok: bool, error?: string}
 */
function llamarAppsScript(string $accion, array $datosExtra): array
{
    global $CONFIG;
    $webhook  = $CONFIG['hoja']['webhook'] ?? '';
    $password = $CONFIG['hoja']['password'] ?? '';

    if ($webhook === '') {
        return ['ok' => false, 'error' => 'Apps Script no configurado.'];
    }

    $cuerpo = json_encode(['action' => $accion, 'password' => $password, ...$datosExtra], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($webhook);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $cuerpo,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $respuesta = curl_exec($ch);
    $error     = curl_error($ch);
    curl_close($ch);

    if ($respuesta === false) {
        error_log("Apps Script ($accion): $error");
        return ['ok' => false, 'error' => $error];
    }

    $json = json_decode($respuesta, true);
    if (!is_array($json)) {
        error_log("Apps Script ($accion): respuesta no válida: $respuesta");
        return ['ok' => false, 'error' => 'Respuesta no válida del Apps Script.'];
    }

    return $json;
}

/** ¿Está la cafetería abierta según el interruptor del panel (tabla ajustes)? */
function cafeteriaAbierta(): bool
{
    try {
        $valor = bd()->query("SELECT valor FROM ajustes WHERE clave = 'cafeteria_abierta'")->fetchColumn();
        return $valor === false || $valor === '1';
    } catch (PDOException $e) {
        error_log('No se pudo leer el estado de la cafetería: ' . $e->getMessage());
        return true;
    }
}

/** ¿Se aceptan pedidos ahora? Interruptor del panel Y horario configurado. */
function pedidosAbiertos(): bool
{
    return cafeteriaAbierta() && dentroDeHorario();
}

/** ¿Estamos dentro del horario en el que se aceptan pedidos? */
function dentroDeHorario(): bool
{
    global $CONFIG;
    [$desde, $hasta] = $CONFIG['app']['horario_pedidos'] ?? [0, 24];
    $hora = (int) date('G');
    return $hora >= $desde && $hora < $hasta;
}

// ---------------------------------------------------------------------
//  Protección CSRF (para el panel de administración)
// ---------------------------------------------------------------------
function iniciarSesion(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
        session_start();
    }
}

function tokenCsrf(): string
{
    iniciarSesion();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function comprobarCsrf(?string $token): bool
{
    iniciarSesion();
    return is_string($token)
        && !empty($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}
