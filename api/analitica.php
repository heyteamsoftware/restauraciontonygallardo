<?php
/**
 * GET api/analitica.php?rango=7|30|90|todo
 * Devuelve las métricas de ventas y las recomendaciones para el panel
 * de Analítica.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/analitica.php';
require_once __DIR__ . '/../includes/recomendaciones.php';

exigirAdmin(esApi: true);

$rango = (string) ($_GET['rango'] ?? '30');
if (!in_array($rango, ['7', '30', '90', 'todo'], true)) {
    $rango = '30';
}

$metricas = analiticaObtenerMetricas($rango);
$recomendaciones = analiticaGenerarRecomendaciones($metricas);

json([
    'ok'              => true,
    'metricas'        => $metricas,
    'recomendaciones' => $recomendaciones,
]);
