<?php
/**
 * GET api/ping.php
 * Comprobación de conectividad: la usan el indicador de "conectado / sin
 * conexión" de la web del alumno y del panel de productos. No necesita
 * sesión, para poder detectar problemas de red incluso sin haber entrado
 * al panel.
 *
 * Devuelve también si la base de datos responde, para distinguir "el
 * servidor está caído" de "el servidor va bien pero la base de datos no".
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/arranque.php';

$bdOk = true;
try {
    bd()->query('SELECT 1');
} catch (Throwable $e) {
    $bdOk = false;
    error_log('ping.php: la base de datos no responde: ' . $e->getMessage());
}

json([
    'ok'  => true,
    'bd'  => $bdOk,
    'hora' => date('H:i:s'),
]);
