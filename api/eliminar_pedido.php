<?php
/**
 * POST api/eliminar_pedido.php
 * Borra un pedido definitivamente (y sus líneas, por la clave foránea en
 * cascada). A diferencia de "cancelar", esto lo quita por completo del
 * panel y de las estadísticas del día.
 *
 * Un pedido que ha llegado a completarse NO se puede borrar nunca, ni
 * aunque se reabra después: ya tiene un registro permanente fuera de esta
 * base de datos (la fila en Google Sheets y/o el aviso enviado al alumno),
 * y borrarlo aquí dejaría esos registros huérfanos o inconsistentes.
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

$consulta = bd()->prepare('SELECT estado, aviso_enviado, registrado_hoja FROM pedidos WHERE id = ?');
$consulta->execute([$id]);
$pedido = $consulta->fetch();

if (!$pedido) {
    jsonError('El pedido ya no existe.', 404);
}

$yaCompletado = in_array($pedido['estado'], ['completado', 'archivado'], true)
    || $pedido['aviso_enviado']
    || $pedido['registrado_hoja'];

if ($yaCompletado) {
    jsonError('Un pedido que ya se ha completado no se puede borrar, para no perder su registro.', 409);
}

bd()->prepare('DELETE FROM pedidos WHERE id = ?')->execute([$id]);

json(['ok' => true]);
