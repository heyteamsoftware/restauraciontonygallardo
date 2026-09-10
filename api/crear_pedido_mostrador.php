<?php
/**
 * POST api/crear_pedido_mostrador.php
 * Crea un pedido manualmente desde el panel de venta en mostrador
 * (admin/venta.php), para clientes atendidos en persona en la cafetería.
 *
 * A diferencia de api/crear_pedido.php, aquí no se pide ni valida DNI: el
 * propio personal de la cafetería ve a la persona delante. El pedido entra
 * en el flujo normal (pendiente → en_curso → completado) igual que los
 * hechos desde el móvil.
 *
 * Cuerpo (JSON): { nombre, csrf, lineas: [{ producto_id, cantidad }] }
 * Respuesta: { ok: true, codigo: "1234A", total: 5.5 }
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

exigirAdmin(esApi: true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido.', 405);
}

$datos = cuerpoJson();

if (!comprobarCsrf($datos['csrf'] ?? null)) {
    jsonError('Petición no válida. Recarga la página.', 403);
}

$nombre = trim((string) ($datos['nombre'] ?? ''));
if ($nombre === '') {
    $nombre = 'Venta en mostrador';
}
if (mb_strlen($nombre) > 120) {
    $nombre = mb_substr($nombre, 0, 120);
}

// ---------------------------------------------------------------------
//  Validación de las líneas (igual que en api/crear_pedido.php)
// ---------------------------------------------------------------------
$errores = [];
$lineasRecibidas = is_array($datos['lineas'] ?? null) ? $datos['lineas'] : [];
$maxPorProducto  = (int) $CONFIG['app']['max_por_producto'];

$cantidades = [];
foreach ($lineasRecibidas as $linea) {
    $id       = (int) ($linea['producto_id'] ?? 0);
    $cantidad = (int) ($linea['cantidad'] ?? 0);

    if ($id <= 0 || $cantidad <= 0) {
        continue;
    }
    if ($cantidad > $maxPorProducto) {
        $errores['productos'] = "Máximo $maxPorProducto unidades de cada producto.";
        break;
    }
    $cantidades[$id] = ($cantidades[$id] ?? 0) + $cantidad;
}

if (!$cantidades && !isset($errores['productos'])) {
    $errores['productos'] = 'Elige al menos un producto.';
}

if ($errores) {
    json(['ok' => false, 'errores' => $errores], 422);
}

// ---------------------------------------------------------------------
//  Se recuperan los productos reales y se calcula el total
// ---------------------------------------------------------------------
$marcadores = implode(',', array_fill(0, count($cantidades), '?'));
$consulta = bd()->prepare(
    "SELECT id, nombre, precio FROM productos WHERE id IN ($marcadores) AND activo = 1"
);
$consulta->execute(array_keys($cantidades));
$productos = $consulta->fetchAll();

if (count($productos) !== count($cantidades)) {
    jsonError('Alguno de los productos ya no está disponible. Actualiza la página.', 409);
}

$total  = 0.0;
$lineas = [];
foreach ($productos as $producto) {
    $cantidad = $cantidades[(int) $producto['id']];
    $precio   = (float) $producto['precio'];
    $total   += $precio * $cantidad;

    $lineas[] = [
        'producto_id'     => (int) $producto['id'],
        'nombre_producto' => $producto['nombre'],
        'precio_unitario' => $precio,
        'cantidad'        => $cantidad,
    ];
}

// ---------------------------------------------------------------------
//  Guardado (pedido + líneas en una única transacción)
// ---------------------------------------------------------------------
$pdo = bd();
$pdo->beginTransaction();

try {
    for ($intento = 1; ; $intento++) {
        $codigo = generarCodigo();
        try {
            $insertar = $pdo->prepare(
                'INSERT INTO pedidos (codigo, dni, nombre, email, estado, total, notas, creado_en, actualizado_en)
                 VALUES (?, "", ?, "", "pendiente", ?, NULL, NOW(), NOW())'
            );
            $insertar->execute([$codigo, $nombre, $total]);
            break;
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000' || $intento >= 5) {
                throw $e;
            }
        }
    }

    $pedidoId = (int) $pdo->lastInsertId();

    $insertarLinea = $pdo->prepare(
        'INSERT INTO pedido_lineas (pedido_id, producto_id, nombre_producto, precio_unitario, cantidad)
         VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($lineas as $linea) {
        $insertarLinea->execute([
            $pedidoId,
            $linea['producto_id'],
            $linea['nombre_producto'],
            $linea['precio_unitario'],
            $linea['cantidad'],
        ]);
    }

    $errorStock = descontarStock($pdo, $lineas);
    if ($errorStock !== null) {
        $pdo->rollBack();
        jsonError($errorStock, 409);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('Error al guardar el pedido de mostrador: ' . $e->getMessage());
    jsonError('No hemos podido guardar el pedido. Inténtalo de nuevo.', 500);
}

json([
    'ok'     => true,
    'codigo' => $codigo,
    'total'  => round($total, 2),
]);
