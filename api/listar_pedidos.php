<?php
/**
 * GET api/listar_pedidos.php
 * Devuelve los pedidos del día para el panel. Se llama cada pocos segundos.
 *
 * Parámetros opcionales:
 *   fecha=AAAA-MM-DD   día a consultar (por defecto, hoy)
 *   firma=...          firma devuelta en la llamada anterior; si no ha
 *                      cambiado nada, la respuesta viene sin datos y así
 *                      se ahorra ancho de banda.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

exigirAdmin(esApi: true);

// Los pedidos completados pasan solos a "archivado" 6 horas después de
// completarse. No hay tarea programada (cron) en este hosting, así que se
// revisa aquí, en cada consulta del panel — es barato y el panel se
// consulta cada pocos segundos, así que en la práctica es casi inmediato.
bd()->exec(
    "UPDATE pedidos SET estado = 'archivado'
      WHERE estado = 'completado' AND actualizado_en <= NOW() - INTERVAL 6 HOUR"
);

$fecha = (string) ($_GET['fecha'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    $fecha = date('Y-m-d');
}

// Firma del estado actual: cuántos pedidos hay y cuándo se tocó el último.
$firmaActual = bd()->prepare(
    'SELECT CONCAT(COUNT(*), "-", COALESCE(MAX(actualizado_en), "0"))
       FROM pedidos WHERE DATE(creado_en) = ?'
);
$firmaActual->execute([$fecha]);
$firma = (string) $firmaActual->fetchColumn();

if (isset($_GET['firma']) && $_GET['firma'] === $firma) {
    json(['ok' => true, 'sin_cambios' => true, 'firma' => $firma]);
}

$consulta = bd()->prepare(
    'SELECT id, codigo, nombre, email, estado, total, notas, aviso_enviado, registrado_hoja,
            creado_en, actualizado_en
       FROM pedidos
      WHERE DATE(creado_en) = ?
      ORDER BY FIELD(estado, "pendiente", "en_curso", "completado", "archivado", "cancelado"), creado_en'
);
$consulta->execute([$fecha]);
$pedidos = $consulta->fetchAll();

// Las líneas de todos los pedidos en una sola consulta.
$lineasPorPedido = [];
if ($pedidos) {
    $ids = array_column($pedidos, 'id');
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $consultaLineas = bd()->prepare(
        "SELECT pedido_id, nombre_producto, precio_unitario, cantidad
           FROM pedido_lineas WHERE pedido_id IN ($marcadores) ORDER BY id"
    );
    $consultaLineas->execute($ids);
    foreach ($consultaLineas->fetchAll() as $linea) {
        $lineasPorPedido[(int) $linea['pedido_id']][] = [
            'nombre'   => $linea['nombre_producto'],
            'cantidad' => (int) $linea['cantidad'],
            'precio'   => (float) $linea['precio_unitario'],
        ];
    }
}

$resultado = [];
$resumen = ['pendiente' => 0, 'en_curso' => 0, 'completado' => 0, 'archivado' => 0, 'cancelado' => 0];

foreach ($pedidos as $pedido) {
    $id = (int) $pedido['id'];
    $resumen[$pedido['estado']]++;

    $resultado[] = [
        'id'            => $id,
        'codigo'        => $pedido['codigo'],
        'nombre'        => $pedido['nombre'],
        'email'         => $pedido['email'],
        'estado'        => $pedido['estado'],
        'total'         => (float) $pedido['total'],
        'notas'         => $pedido['notas'],
        'aviso_enviado' => (bool) $pedido['aviso_enviado'],
        'registrado_hoja' => (bool) $pedido['registrado_hoja'],
        'hora'          => date('H:i', strtotime($pedido['creado_en'])),
        'lineas'        => $lineasPorPedido[$id] ?? [],
    ];
}

json([
    'ok'      => true,
    'firma'   => $firma,
    'fecha'   => $fecha,
    'resumen' => $resumen,
    'pedidos' => $resultado,
]);
