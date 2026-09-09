<?php
/**
 * POST api/crear_pedido.php
 * Recibe el pedido del alumno, lo valida y lo guarda.
 *
 * Cuerpo (JSON):
 *   { nombre, dni, email, notas, lineas: [{ producto_id, cantidad }] }
 * Respuesta:
 *   { ok: true, codigo: "C-4F7B", total: 5.5 }
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/arranque.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido.', 405);
}

if (!dentroDeHorario()) {
    jsonError('Ahora mismo no se admiten pedidos. Consulta el horario de la cafetería.', 409);
}

$datos = cuerpoJson();

// ---------------------------------------------------------------------
//  Validación de los datos personales
// ---------------------------------------------------------------------
$errores = [];

$nombre = trim((string) ($datos['nombre'] ?? ''));
if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 120) {
    $errores['nombre'] = 'Escribe tu nombre y apellidos.';
}

$dni = normalizarDni((string) ($datos['dni'] ?? ''));
if (!dniValido($dni)) {
    $errores['dni'] = 'El DNI no es válido. Revisa los números y la letra.';
}

$email = trim((string) ($datos['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
    $errores['email'] = 'El correo electrónico no es válido.';
}

$notas = trim((string) ($datos['notas'] ?? ''));
if (mb_strlen($notas) > 255) {
    $notas = mb_substr($notas, 0, 255);
}

// ---------------------------------------------------------------------
//  Validación de las líneas
//  Los precios se toman SIEMPRE de la base de datos, nunca del navegador.
// ---------------------------------------------------------------------
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
    // Si el mismo producto llega repetido, se acumula.
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
//  Freno básico contra el spam: pocos pedidos activos por DNI
// ---------------------------------------------------------------------
$activos = bd()->prepare(
    "SELECT COUNT(*) FROM pedidos
      WHERE dni = ? AND estado IN ('pendiente','en_curso') AND DATE(creado_en) = CURDATE()"
);
$activos->execute([$dni]);
if ((int) $activos->fetchColumn() >= 3) {
    jsonError('Ya tienes varios pedidos pendientes de recoger. Pásate por la cafetería primero.', 429);
}

// ---------------------------------------------------------------------
//  Guardado (pedido + líneas en una única transacción)
// ---------------------------------------------------------------------
$pdo = bd();
$pdo->beginTransaction();

try {
    // El código es único; si hubiera coincidencia se reintenta.
    for ($intento = 1; ; $intento++) {
        $codigo = generarCodigo();
        try {
            $insertar = $pdo->prepare(
                'INSERT INTO pedidos (codigo, nombre, dni, email, estado, total, notas, creado_en, actualizado_en)
                 VALUES (?, ?, ?, ?, "pendiente", ?, ?, NOW(), NOW())'
            );
            $insertar->execute([$codigo, $nombre, $dni, $email, $total, $notas ?: null]);
            break;
        } catch (PDOException $e) {
            // 23000 = clave duplicada. Cualquier otro error se propaga.
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

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('Error al guardar el pedido: ' . $e->getMessage());
    jsonError('No hemos podido guardar el pedido. Inténtalo de nuevo.', 500);
}

json([
    'ok'     => true,
    'codigo' => $codigo,
    'total'  => round($total, 2),
]);
