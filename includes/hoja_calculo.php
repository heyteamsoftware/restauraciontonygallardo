<?php
/**
 * Registro de pedidos completados en Google Sheets.
 *
 * Llama por HTTPS a un Apps Script publicado como aplicación web (ver
 * sql/../README para el código y los pasos de instalación). Si no hay
 * webhook configurado, la función no hace nada y la app sigue funcionando
 * con normalidad.
 */

declare(strict_types=1);

require_once __DIR__ . '/arranque.php';

/**
 * Envía un pedido completado a la hoja de cálculo.
 *
 * @param array $pedido Fila de la tabla pedidos.
 * @param array $lineas Filas de pedido_lineas.
 * @return bool true si se envió correctamente.
 */
function registrarPedidoEnHoja(array $pedido, array $lineas): bool
{
    global $CONFIG;

    $webhook = $CONFIG['hoja']['webhook'] ?? '';
    if ($webhook === '') {
        return false;
    }

    $productos = [];
    foreach ($lineas as $linea) {
        $productos[] = sprintf('%d x %s', $linea['cantidad'], $linea['nombre_producto']);
    }

    $cuerpo = [
        'codigo'    => $pedido['codigo'],
        'nombre'    => $pedido['nombre'],
        'email'     => $pedido['email'],
        'productos' => implode(', ', $productos),
        'notas'     => $pedido['notas'] ?? '',
        'total'     => (float) $pedido['total'],
    ];

    if (!function_exists('curl_init')) {
        error_log('registrarPedidoEnHoja: cURL no está disponible en este servidor.');
        return false;
    }

    $ch = curl_init($webhook);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($cuerpo, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        // Apps Script hace una redirección 302 antes de responder; hay que seguirla.
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    $respuesta = curl_exec($ch);
    $codigo    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errorCurl = curl_error($ch);
    curl_close($ch);

    if ($codigo >= 200 && $codigo < 300) {
        return true;
    }

    error_log(sprintf(
        'Fallo al registrar el pedido %s en la hoja (HTTP %d): %s %s',
        $pedido['codigo'],
        $codigo,
        $errorCurl,
        (string) $respuesta
    ));
    return false;
}
