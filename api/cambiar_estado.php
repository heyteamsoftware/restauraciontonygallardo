<?php
/**
 * POST api/cambiar_estado.php
 * Cambia el estado de un pedido desde el panel.
 *
 * Ni el aviso por correo ni el registro en Google Sheets se hacen aquí:
 * InfinityFree bloquea las conexiones salientes a script.google.com desde
 * el servidor. En su lugar, esta respuesta incluye los datos del pedido
 * (bloque "registro") para que sea el propio navegador del panel
 * (assets/js/panel.js) quien llame al Apps Script directamente —el
 * navegador del admin sí puede alcanzarlo— y luego confirme con
 * api/marcar_registrado_hoja.php y api/marcar_aviso_enviado.php.
 *
 * Cuerpo (JSON): { id, estado, csrf }
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
//  Al completar el pedido: se prepara lo necesario para que el
//  navegador avise por correo y registre en el Sheet (cada cosa una
//  sola vez, aunque el pedido se reabra y se vuelva a completar).
// -----------------------------------------------------------------
$aviso    = 'no_procede';
$registro = null;

if ($estado === 'completado') {
    $necesitaAviso = !$pedido['aviso_enviado'];
    $necesitaHoja  = !$pedido['registrado_hoja'];

    if ($necesitaAviso && empty($pedido['email'])) {
        // El alumno no dejó correo: no hay a quién avisar.
        $necesitaAviso = false;
        $aviso = 'sin_email';
    }

    if ($necesitaAviso || $necesitaHoja) {
        $consultaLineas = bd()->prepare('SELECT * FROM pedido_lineas WHERE pedido_id = ? ORDER BY id');
        $consultaLineas->execute([$id]);

        $productos = [];
        foreach ($consultaLineas->fetchAll() as $linea) {
            $productos[] = sprintf('%d x %s', $linea['cantidad'], $linea['nombre_producto']);
        }

        $registro = [
            'id'             => $id,
            'necesitaAviso'  => $necesitaAviso,
            'necesitaHoja'   => $necesitaHoja,
            'codigo'         => $pedido['codigo'],
            'nombre'         => $pedido['nombre'],
            'email'          => $pedido['email'],
            'productos'      => implode(', ', $productos),
            'notas'          => $pedido['notas'] ?? '',
            'total'          => (float) $pedido['total'],
        ];

        if ($necesitaAviso) {
            $aviso = null; // el navegador dirá si se ha enviado o no
        }
    }
}

json(['ok' => true, 'estado' => $estado, 'aviso' => $aviso, 'registro' => $registro]);
