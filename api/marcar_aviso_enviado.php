<?php
/**
 * POST api/marcar_aviso_enviado.php
 * El navegador del panel llama a esto justo después de haber enviado con
 * éxito el aviso por correo (vía Apps Script), para que el servidor no lo
 * vuelva a intentar si el pedido se reabre y se completa de nuevo.
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

bd()->prepare('UPDATE pedidos SET aviso_enviado = 1 WHERE id = ?')->execute([$id]);

json(['ok' => true]);
