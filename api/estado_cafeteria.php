<?php
/**
 * GET  api/estado_cafeteria.php            → { ok, abierto (interruptor + horario), interruptor }  (público)
 * POST api/estado_cafeteria.php { abierto, csrf }  → abre/cierra (solo admin)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json(['ok' => true, 'abierto' => pedidosAbiertos(), 'interruptor' => cafeteriaAbierta()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido.', 405);
}

exigirAdmin(esApi: true);

$datos = cuerpoJson();
if (!comprobarCsrf($datos['csrf'] ?? null)) {
    jsonError('Petición no válida. Recarga la página.', 403);
}

$abierto = !empty($datos['abierto']) ? '1' : '0';
bd()->prepare(
    "INSERT INTO ajustes (clave, valor) VALUES ('cafeteria_abierta', ?)
     ON DUPLICATE KEY UPDATE valor = VALUES(valor)"
)->execute([$abierto]);

json(['ok' => true, 'abierto' => $abierto === '1']);
