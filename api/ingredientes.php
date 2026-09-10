<?php
/**
 * Gestión de ingredientes (pestaña "Stock" del panel): pan, embutidos,
 * lo que sea. No se venden directamente, pero se pueden vincular a
 * productos (ver api/productos.php) para que su stock limite cuántas
 * unidades de ese producto se pueden vender.
 *
 *   GET  api/ingredientes.php                         → lista completa
 *   POST api/ingredientes.php { accion: "crear",   nombre, stock, csrf }
 *   POST api/ingredientes.php { accion: "editar",  id, nombre, stock, csrf }
 *   POST api/ingredientes.php { accion: "eliminar", id, csrf }
 *
 * stock es opcional: un entero >= 0, o null/vacío para sin límite.
 * Un ingrediente que esté siendo usado en alguna receta no se puede
 * borrar (hay que quitarlo antes de los productos que lo usan).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

exigirAdmin(esApi: true);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $ingredientes = bd()->query(
        'SELECT id, nombre, stock FROM ingredientes ORDER BY nombre'
    )->fetchAll();

    $usos = bd()->query(
        'SELECT pi.ingrediente_id, p.nombre
           FROM producto_ingredientes pi
           JOIN productos p ON p.id = pi.producto_id
       ORDER BY p.nombre'
    )->fetchAll();
    $usadoEn = [];
    foreach ($usos as $uso) {
        $usadoEn[(int) $uso['ingrediente_id']][] = $uso['nombre'];
    }

    foreach ($ingredientes as &$ingrediente) {
        $ingrediente['id']       = (int) $ingrediente['id'];
        $ingrediente['stock']    = $ingrediente['stock'] !== null ? (int) $ingrediente['stock'] : null;
        $ingrediente['usado_en'] = $usadoEn[$ingrediente['id']] ?? [];
    }
    unset($ingrediente);

    json(['ok' => true, 'ingredientes' => $ingredientes]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido.', 405);
}

$datos = cuerpoJson();

if (!comprobarCsrf($datos['csrf'] ?? null)) {
    jsonError('Petición no válida. Recarga la página.', 403);
}

$accion = (string) ($datos['accion'] ?? '');

/** Valida y normaliza el campo stock: '' o null = ilimitado. */
function validarStockIngrediente($crudo): array
{
    if ($crudo === null || $crudo === '') {
        return [true, null];
    }
    if (!is_numeric($crudo) || (int) $crudo != $crudo || (int) $crudo < 0) {
        return [false, null];
    }
    return [true, (int) $crudo];
}

switch ($accion) {

    case 'crear':
    case 'editar':
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        [$stockValido, $stock] = validarStockIngrediente($datos['stock'] ?? null);

        $errores = [];
        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 100) {
            $errores['nombre'] = 'El nombre debe tener entre 2 y 100 caracteres.';
        }
        if (!$stockValido) {
            $errores['stock'] = 'El stock debe ser un número entero de 0 o más (o dejarlo en blanco para ilimitado).';
        }
        if ($errores) {
            json(['ok' => false, 'errores' => $errores], 422);
        }

        if ($accion === 'crear') {
            try {
                bd()->prepare('INSERT INTO ingredientes (nombre, stock, creado_en) VALUES (?, ?, NOW())')
                    ->execute([$nombre, $stock]);
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    json(['ok' => false, 'errores' => ['nombre' => 'Ya existe un ingrediente con ese nombre.']], 422);
                }
                throw $e;
            }
            json(['ok' => true, 'id' => (int) bd()->lastInsertId()]);
        }

        $id = (int) ($datos['id'] ?? 0);
        if ($id <= 0) {
            jsonError('Ingrediente no indicado.', 422);
        }
        try {
            bd()->prepare('UPDATE ingredientes SET nombre = ?, stock = ? WHERE id = ?')
                ->execute([$nombre, $stock, $id]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                json(['ok' => false, 'errores' => ['nombre' => 'Ya existe un ingrediente con ese nombre.']], 422);
            }
            throw $e;
        }
        json(['ok' => true]);

    case 'eliminar':
        $id = (int) ($datos['id'] ?? 0);
        if ($id <= 0) {
            jsonError('Ingrediente no indicado.', 422);
        }

        $usado = bd()->prepare('SELECT COUNT(*) FROM producto_ingredientes WHERE ingrediente_id = ?');
        $usado->execute([$id]);
        if ((int) $usado->fetchColumn() > 0) {
            jsonError('Este ingrediente se usa en la receta de algún producto. Quítalo de esos productos antes de borrarlo.', 409);
        }

        bd()->prepare('DELETE FROM ingredientes WHERE id = ?')->execute([$id]);
        json(['ok' => true]);

    default:
        jsonError('Acción desconocida.', 422);
}
