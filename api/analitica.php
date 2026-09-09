<?php
/**
 * GET api/analitica.php?rango=7|30|90|todo|curso_AAAA
 * Devuelve las métricas de ventas, las recomendaciones y los cursos
 * escolares disponibles para el panel de Analítica.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/analitica.php';
require_once __DIR__ . '/../includes/recomendaciones.php';

exigirAdmin(esApi: true);

$rangoInicial = analiticaValorCurso(analiticaAnioInicioCursoActual());
$rango = (string) ($_GET['rango'] ?? $rangoInicial);
$rangoValido = in_array($rango, ['7', '30', '90', 'todo'], true) || preg_match('/^curso_\d{4}$/', $rango);
if (!$rangoValido) {
    $rango = $rangoInicial;
}

$metricas = analiticaObtenerMetricas($rango);
$recomendaciones = analiticaGenerarRecomendaciones($metricas);

json([
    'ok'              => true,
    'metricas'        => $metricas,
    'recomendaciones' => $recomendaciones,
    'cursos'          => analiticaCursosDisponibles(),
]);
