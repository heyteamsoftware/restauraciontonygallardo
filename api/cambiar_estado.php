<?php
/**
 * POST api/cambiar_estado.php
 * Cambia el estado de un pedido desde el panel.
 *
 * Al completarse, el propio servidor llama al Apps Script (registrar en
 * el Sheet / mandar el correo de aviso) mediante llamarAppsScript(): así
 * la contraseña del webhook no tiene que viajar al navegador del panel.
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

$estadosValidos = ['pendiente', 'en_curso', 'completado', 'archivado', 'cancelado'];
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

$pdo = bd();

// ---------------------------------------------------------------------
//  Stock: al cancelar un pedido se devuelven sus unidades; si se reabre
//  un pedido cancelado, hay que volver a descontarlas (puede que ya no
//  quede stock suficiente, en cuyo caso se bloquea la reapertura).
// ---------------------------------------------------------------------
if ($estado === 'cancelado' && !$pedido['stock_repuesto']) {
    reponerStock($pdo, lineasDelPedido($pdo, $id));
    $pdo->prepare('UPDATE pedidos SET stock_repuesto = 1 WHERE id = ?')->execute([$id]);
} elseif ($pedido['estado'] === 'cancelado' && $pedido['stock_repuesto']) {
    $errorStock = descontarStock($pdo, lineasDelPedido($pdo, $id));
    if ($errorStock !== null) {
        jsonError($errorStock, 409);
    }
    $pdo->prepare('UPDATE pedidos SET stock_repuesto = 0 WHERE id = ?')->execute([$id]);
}

if ($estado === 'completado') {
    // Marca permanente: una vez completado, ya no se puede borrar el
    // pedido nunca, ni aunque se reabra después (ver api/eliminar_pedido.php).
    $pdo->prepare('UPDATE pedidos SET estado = ?, fue_completado = 1, actualizado_en = NOW() WHERE id = ?')
        ->execute([$estado, $id]);
} else {
    $pdo->prepare('UPDATE pedidos SET estado = ?, actualizado_en = NOW() WHERE id = ?')
        ->execute([$estado, $id]);
}

// -----------------------------------------------------------------
//  Al completar el pedido: se avisa por correo y se registra en el
//  Sheet (cada cosa una sola vez, aunque el pedido se reabra y se
//  vuelva a completar), llamando al Apps Script desde el propio
//  servidor.
// -----------------------------------------------------------------
$aviso = 'no_procede';

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
        $productos = implode(', ', $productos);

        if ($necesitaHoja) {
            $resultado = llamarAppsScript('registrarPedido', [
                'codigo'    => $pedido['codigo'],
                'nombre'    => $pedido['nombre'],
                'email'     => $pedido['email'],
                'productos' => $productos,
                'notas'     => $pedido['notas'] ?? '',
                'total'     => (float) $pedido['total'],
            ]);
            if ($resultado['ok']) {
                $pdo->prepare('UPDATE pedidos SET registrado_hoja = 1 WHERE id = ?')->execute([$id]);
            } else {
                error_log('No se pudo registrar en la hoja: ' . ($resultado['error'] ?? ''));
            }
        }

        if ($necesitaAviso) {
            $cuerpo = "Hola {$pedido['nombre']},\n\n"
                . "Tu pedido está listo para recoger. Enseña este código en la cafetería:\n\n"
                . "  {$pedido['codigo']}\n\n"
                . "Pedido: {$productos}\n"
                . 'Total: ' . number_format((float) $pedido['total'], 2, ',', '.') . ' €'
                . (!empty($pedido['notas']) ? "\n\nNotas: {$pedido['notas']}" : '');

            $resultado = llamarAppsScript('email', [
                'to'     => $pedido['email'],
                'asunto' => "Tu pedido {$pedido['codigo']} ya está listo",
                'cuerpo' => $cuerpo,
            ]);
            if ($resultado['ok']) {
                $pdo->prepare('UPDATE pedidos SET aviso_enviado = 1 WHERE id = ?')->execute([$id]);
                $aviso = 'ok';
            } else {
                error_log('No se pudo enviar el aviso por correo: ' . ($resultado['error'] ?? ''));
                $aviso = 'fallido';
            }
        }
    }
}

json(['ok' => true, 'estado' => $estado, 'aviso' => $aviso]);
