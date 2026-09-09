<?php
/**
 * POST api/eliminar_pedido.php
 * Borra un pedido definitivamente (y sus líneas, por la clave foránea en
 * cascada). A diferencia de "cancelar", esto lo quita por completo del
 * panel y de las estadísticas del día.
 *
 * Cuerpo (JSON): { id, csrf }
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

$id = (int) ($datos['id'] ?? 0);
if ($id <= 0) {
    jsonError('Pedido no indicado.', 422);
}

$borrar = bd()->prepare('DELETE FROM pedidos WHERE id = ?');
$borrar->execute([$id]);

if ($borrar->rowCount() === 0) {
    jsonError('El pedido ya no existe.', 404);
}

json(['ok' => true]);
