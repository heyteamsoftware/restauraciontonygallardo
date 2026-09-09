<?php
/**
 * POST api/marcar_registrado_hoja.php
 * El navegador del panel llama a esto justo después de haber registrado con
 * éxito un pedido en el Apps Script de Google Sheets, para que el servidor
 * no lo vuelva a intentar (evita filas duplicadas si el pedido se reabre).
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

bd()->prepare('UPDATE pedidos SET registrado_hoja = 1 WHERE id = ?')->execute([$id]);

json(['ok' => true]);
