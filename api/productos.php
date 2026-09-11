<?php
/**
 * Gestión del catálogo desde el panel.
 *
 *   GET  api/productos.php                       → lista completa
 *   POST api/productos.php  { accion: "crear",    nombre, categoria, precio, icono, ingredientes, csrf }
 *   POST api/productos.php  { accion: "editar",   id, nombre, categoria, precio, icono, ingredientes, csrf }
 *   POST api/productos.php  { accion: "activar",  id, activo, csrf }
 *   POST api/productos.php  { accion: "eliminar", id, csrf }
 *
 * icono es opcional: el nombre de archivo de assets/img/productos/, o null
 * para no mostrar ninguno. Debe ser uno de los del catálogo de
 * includes/iconos_productos.php — cualquier otro valor se rechaza.
 *
 * ingredientes es obligatorio: [{ ingrediente_id, cantidad }, ...], al menos
 * uno. Es lo único de donde sale el stock del producto (ver
 * includes/arranque.php:catalogoConStock()); no existe un stock manual.
 * Sustituye por completo la receta del producto (borra y vuelve a
 * insertar). Cada unidad vendida descuenta "cantidad" unidades de cada
 * ingrediente de la lista (ver includes/arranque.php:descontarStock()).
 *
 * Un producto que ya aparece en algún pedido no se borra: se desactiva, para
 * no perder el histórico.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/iconos_productos.php';

exigirAdmin(esApi: true);

// ---------------------------------------------------------------------
//  Lectura
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json(['ok' => true, 'productos' => catalogoConStock(soloActivos: false), 'iconos' => iconosProductosDisponibles()]);
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
        $icono     = trim((string) ($datos['icono'] ?? ''));
        $icono     = $icono !== '' ? $icono : null;

        // Receta: lista de { ingrediente_id, cantidad }, obligatoria (al
        // menos una fila). Se valida que cada ingrediente exista y que la
        // cantidad sea un entero >= 1.
        $ingredientesCrudos = is_array($datos['ingredientes'] ?? null) ? $datos['ingredientes'] : [];
        $receta = [];
        $ingredientesValidos = true;
        foreach ($ingredientesCrudos as $item) {
            $ingredienteId = (int) ($item['ingrediente_id'] ?? 0);
            $cantidad      = (int) ($item['cantidad'] ?? 0);
            if ($ingredienteId <= 0 || $cantidad < 1) {
                $ingredientesValidos = false;
                break;
            }
            $receta[$ingredienteId] = $cantidad; // por id: si se repite, se queda la última
        }
        if (!$receta) {
            $ingredientesValidos = false;
        } elseif ($ingredientesValidos) {
            $marcadores = implode(',', array_fill(0, count($receta), '?'));
            $existentes = bd()->prepare("SELECT COUNT(*) FROM ingredientes WHERE id IN ($marcadores)");
            $existentes->execute(array_keys($receta));
            if ((int) $existentes->fetchColumn() !== count($receta)) {
                $ingredientesValidos = false;
            }
        }

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
        if (!$ingredientesValidos) {
            $errores['ingredientes'] = 'Añade al menos un ingrediente válido: es lo que determina el stock del producto.';
        }
        if (!iconoProductoValido($icono)) {
            $errores['icono'] = 'Ese icono no existe. Elige uno de la lista.';
        }
        if ($errores) {
            json(['ok' => false, 'errores' => $errores], 422);
        }

        $pdo = bd();
        $pdo->beginTransaction();

        if ($accion === 'crear') {
            $siguienteOrden = (int) $pdo->query('SELECT COALESCE(MAX(orden), 0) + 1 FROM productos')->fetchColumn();
            $pdo->prepare(
                'INSERT INTO productos (nombre, categoria, precio, stock, icono, activo, orden, creado_en)
                 VALUES (?, ?, ?, NULL, ?, 1, ?, NOW())'
            )->execute([$nombre, $categoria, $precio, $icono, $siguienteOrden]);
            $id = (int) $pdo->lastInsertId();
        } else {
            $id = (int) ($datos['id'] ?? 0);
            if ($id <= 0) {
                $pdo->rollBack();
                jsonError('Producto no indicado.', 422);
            }
            $pdo->prepare('UPDATE productos SET nombre = ?, categoria = ?, precio = ?, stock = NULL, icono = ? WHERE id = ?')
                ->execute([$nombre, $categoria, $precio, $icono, $id]);
        }

        $pdo->prepare('DELETE FROM producto_ingredientes WHERE producto_id = ?')->execute([$id]);
        if ($receta) {
            $insertarReceta = $pdo->prepare(
                'INSERT INTO producto_ingredientes (producto_id, ingrediente_id, cantidad) VALUES (?, ?, ?)'
            );
            foreach ($receta as $ingredienteId => $cantidad) {
                $insertarReceta->execute([$id, $ingredienteId, $cantidad]);
            }
        }

        $pdo->commit();

        json(['ok' => true, 'id' => $id]);

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
