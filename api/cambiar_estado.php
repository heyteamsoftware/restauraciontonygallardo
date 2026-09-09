<?php
/**
 * POST api/cambiar_estado.php
 * Cambia el estado de un pedido desde el panel.
 * Al pasarlo a "completado" se avisa al alumno por correo (una sola vez).
 *
 * Cuerpo (JSON): { id, estado, csrf }
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/email.php';

exigirAdmin(esApi: true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido.', 405);
}

$datos = cuerpoJson();

if (!comprobarCsrf($datos['csrf'] ?? null)) {
    jsonError('Petición no válida. Recarga la página.', 403);
}

$id     = (int) ($datos['id'] ?? 0);
$estado = (string) ($datos['estado'] ?? '');

$estadosValidos = ['pendiente', 'en_curso', 'completado', 'cancelado'];
if ($id <= 0 || !in_array($estado, $estadosValidos, true)) {
    jsonError('Datos incorrectos.', 422);
}

$consulta = bd()->prepare('SELECT * FROM pedidos WHERE id = ?');
$consulta->execute([$id]);
$pedido = $consulta->fetch();

if (!$pedido) {
    jsonError('El pedido no existe.', 404);
}

if ($pedido['estado'] === $estado) {
    json(['ok' => true, 'estado' => $estado, 'aviso' => 'sin_cambios']);
}

bd()->prepare('UPDATE pedidos SET estado = ?, actualizado_en = NOW() WHERE id = ?')
    ->execute([$estado, $id]);

// -----------------------------------------------------------------
//  Aviso al alumno cuando el pedido queda listo
// -----------------------------------------------------------------
$aviso = 'no_procede';

if ($estado === 'completado' && !$pedido['aviso_enviado']) {
    $lineas = bd()->prepare('SELECT * FROM pedido_lineas WHERE pedido_id = ? ORDER BY id');
    $lineas->execute([$id]);

    $pedido['estado'] = $estado;
    $enviado = avisarPedidoListo($pedido, $lineas->fetchAll());

    if ($enviado) {
        bd()->prepare('UPDATE pedidos SET aviso_enviado = 1 WHERE id = ?')->execute([$id]);
        $aviso = 'enviado';
    } else {
        // El pedido queda completado igualmente; solo falló el correo.
        $aviso = 'fallido';
    }
}

json(['ok' => true, 'estado' => $estado, 'aviso' => $aviso]);
