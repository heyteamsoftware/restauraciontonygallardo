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
require_once __DIR__ . '/../includes/hoja_calculo.php';

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
//  Al completar el pedido: aviso por correo y registro en la hoja
//  (cada uno una sola vez, aunque el pedido se reabra y se vuelva a
//  completar más adelante).
// -----------------------------------------------------------------
$aviso = 'no_procede';

if ($estado === 'completado') {
    $pedido['estado'] = $estado;

    $necesitaLineas = !$pedido['aviso_enviado'] || !$pedido['registrado_hoja'];
    $lineas = [];
    if ($necesitaLineas) {
        $consultaLineas = bd()->prepare('SELECT * FROM pedido_lineas WHERE pedido_id = ? ORDER BY id');
        $consultaLineas->execute([$id]);
        $lineas = $consultaLineas->fetchAll();
    }

    if (!$pedido['aviso_enviado']) {
        if (empty($pedido['email'])) {
            // El alumno no dejó correo: no hay a quién avisar.
            $aviso = 'sin_email';
        } else {
            $enviado = avisarPedidoListo($pedido, $lineas);
            if ($enviado) {
                bd()->prepare('UPDATE pedidos SET aviso_enviado = 1 WHERE id = ?')->execute([$id]);
                $aviso = 'enviado';
            } else {
                // El pedido queda completado igualmente; solo falló el correo.
                $aviso = 'fallido';
            }
        }
    }

    if (!$pedido['registrado_hoja']) {
        if (registrarPedidoEnHoja($pedido, $lineas)) {
            bd()->prepare('UPDATE pedidos SET registrado_hoja = 1 WHERE id = ?')->execute([$id]);
        }
        // Si falla, no se bloquea nada: el pedido sigue completado igual.
    }
}

json(['ok' => true, 'estado' => $estado, 'aviso' => $aviso]);
