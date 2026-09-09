<?php
/**
 * POST api/cambiar_estado.php
 * Cambia el estado de un pedido desde el panel.
 * Al pasarlo a "completado" se avisa al alumno por correo (una sola vez).
 *
 * El registro en Google Sheets NO se hace aquí: InfinityFree bloquea las
 * conexiones salientes a script.google.com desde el servidor. En su lugar,
 * esta respuesta incluye los datos del pedido (bloque "hoja") para que sea
 * el propio navegador del panel (assets/js/panel.js) quien llame al Apps
 * Script directamente, y luego confirme con api/marcar_registrado_hoja.php.
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
//  Al completar el pedido: aviso por correo (una sola vez) y datos
//  para que el navegador registre el pedido en la hoja de cálculo.
// -----------------------------------------------------------------
$aviso = 'no_procede';
$hoja  = null;

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
        $productos = [];
        foreach ($lineas as $linea) {
            $productos[] = sprintf('%d x %s', $linea['cantidad'], $linea['nombre_producto']);
        }

        $hoja = [
            'id'        => $id,
            'codigo'    => $pedido['codigo'],
            'nombre'    => $pedido['nombre'],
            'email'     => $pedido['email'],
            'productos' => implode(', ', $productos),
            'notas'     => $pedido['notas'] ?? '',
            'total'     => (float) $pedido['total'],
        ];
    }
}

json(['ok' => true, 'estado' => $estado, 'aviso' => $aviso, 'hoja' => $hoja]);
