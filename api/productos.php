<?php
/**
 * Gestión del catálogo desde el panel.
 *
 *   GET  api/productos.php                       → lista completa
 *   POST api/productos.php  { accion: "crear",    nombre, categoria, precio, csrf }
 *   POST api/productos.php  { accion: "editar",   id, nombre, categoria, precio, csrf }
 *   POST api/productos.php  { accion: "activar",  id, activo, csrf }
 *   POST api/productos.php  { accion: "eliminar", id, csrf }
 *
 * Un producto que ya aparece en algún pedido no se borra: se desactiva, para
 * no perder el histórico.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

exigirAdmin(esApi: true);

// ---------------------------------------------------------------------
//  Lectura
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $productos = bd()->query(
        'SELECT id, nombre, categoria, precio, activo, orden
           FROM productos ORDER BY orden, id'
    )->fetchAll();

    foreach ($productos as &$producto) {
        $producto['id']     = (int) $producto['id'];
        $producto['precio'] = (float) $producto['precio'];
        $producto['activo'] = (bool) $producto['activo'];
        $producto['orden']  = (int) $producto['orden'];
    }
    unset($producto);

    json(['ok' => true, 'productos' => $productos]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido.', 405);
}

// ---------------------------------------------------------------------
//  Escritura
// ---------------------------------------------------------------------
$datos = cuerpoJson();

if (!comprobarCsrf($datos['csrf'] ?? null)) {
    jsonError('Petición no válida. Recarga la página.', 403);
}

$accion = (string) ($datos['accion'] ?? '');

switch ($accion) {

    case 'crear':
    case 'editar':
        $nombre    = trim((string) ($datos['nombre'] ?? ''));
        $categoria = trim((string) ($datos['categoria'] ?? ''));
        $precio    = (float) ($datos['precio'] ?? 0);

        $errores = [];
        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 100) {
            $errores['nombre'] = 'El nombre debe tener entre 2 y 100 caracteres.';
        }
        if (mb_strlen($categoria) < 2 || mb_strlen($categoria) > 50) {
            $errores['categoria'] = 'Indica una categoría (Bocatas, Croasants...).';
        }
        if ($precio < 0 || $precio > 999.99) {
            $errores['precio'] = 'El precio no es válido.';
        }
        if ($errores) {
            json(['ok' => false, 'errores' => $errores], 422);
        }

        if ($accion === 'crear') {
            $siguienteOrden = (int) bd()->query('SELECT COALESCE(MAX(orden), 0) + 1 FROM productos')->fetchColumn();
            bd()->prepare(
                'INSERT INTO productos (nombre, categoria, precio, activo, orden, creado_en)
                 VALUES (?, ?, ?, 1, ?, NOW())'
            )->execute([$nombre, $categoria, $precio, $siguienteOrden]);

            json(['ok' => true, 'id' => (int) bd()->lastInsertId()]);
        }

        $id = (int) ($datos['id'] ?? 0);
        if ($id <= 0) {
            jsonError('Producto no indicado.', 422);
        }
        bd()->prepare('UPDATE productos SET nombre = ?, categoria = ?, precio = ? WHERE id = ?')
            ->execute([$nombre, $categoria, $precio, $id]);

        json(['ok' => true]);

    case 'activar':
        $id     = (int) ($datos['id'] ?? 0);
        $activo = !empty($datos['activo']) ? 1 : 0;
        if ($id <= 0) {
            jsonError('Producto no indicado.', 422);
        }
        bd()->prepare('UPDATE productos SET activo = ? WHERE id = ?')->execute([$activo, $id]);

        json(['ok' => true, 'activo' => (bool) $activo]);

    case 'eliminar':
        $id = (int) ($datos['id'] ?? 0);
        if ($id <= 0) {
            jsonError('Producto no indicado.', 422);
        }

        $usado = bd()->prepare('SELECT COUNT(*) FROM pedido_lineas WHERE producto_id = ?');
        $usado->execute([$id]);

        if ((int) $usado->fetchColumn() > 0) {
            bd()->prepare('UPDATE productos SET activo = 0 WHERE id = ?')->execute([$id]);
            json([
                'ok'      => true,
                'accion'  => 'desactivado',
                'mensaje' => 'El producto aparece en pedidos anteriores, así que se ha desactivado en lugar de borrarlo.',
            ]);
        }

        bd()->prepare('DELETE FROM productos WHERE id = ?')->execute([$id]);
        json(['ok' => true, 'accion' => 'eliminado']);

    default:
        jsonError('Acción desconocida.', 422);
}
