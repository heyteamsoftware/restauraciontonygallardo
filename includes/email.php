<?php
/**
 * Envío de avisos por correo.
 *
 * IMPORTANTE: InfinityFree bloquea la función mail() de PHP y las conexiones
 * SMTP salientes. Por eso el envío se hace llamando por HTTPS a la API de un
 * proveedor externo con plan gratuito (Brevo o Resend), que sí funciona.
 *
 * Si en config.php el proveedor es 'ninguno', las funciones no envían nada y
 * devuelven false: la aplicación sigue funcionando con normalidad.
 */

declare(strict_types=1);

require_once __DIR__ . '/arranque.php';

/**
 * Avisa al alumno de que su pedido está listo para recoger.
 *
 * @param array $pedido Fila de la tabla pedidos.
 * @param array $lineas Filas de pedido_lineas.
 * @return bool true si el correo salió de verdad.
 */
function avisarPedidoListo(array $pedido, array $lineas): bool
{
    global $CONFIG;

    $resumen = [];
    foreach ($lineas as $linea) {
        $resumen[] = sprintf('%d × %s', $linea['cantidad'], $linea['nombre_producto']);
    }

    $asunto = sprintf('Tu pedido %s ya está listo', $pedido['codigo']);

    $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#23231f">'
        . '<h2 style="color:#1f3d2b;margin:0 0 12px">¡Tu pedido está listo!</h2>'
        . '<p>Hola ' . htmlspecialchars($pedido['nombre'], ENT_QUOTES, 'UTF-8') . ',</p>'
        . '<p>Puedes pasar a recogerlo por la cafetería. Indica este código:</p>'
        . '<p style="font-size:26px;font-weight:bold;letter-spacing:2px;color:#1f3d2b">'
        . htmlspecialchars($pedido['codigo'], ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>Tu pedido:</strong><br>'
        . htmlspecialchars(implode('<br>', $resumen), ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>Total:</strong> ' . number_format((float) $pedido['total'], 2, ',', '.') . ' €</p>'
        . '<hr style="border:none;border-top:1px solid #e2dacb;margin:20px 0">'
        . '<p style="font-size:13px;color:#5c5c53">'
        . htmlspecialchars($CONFIG['app']['nombre'], ENT_QUOTES, 'UTF-8')
        . ' · Este mensaje es automático, no respondas a este correo.</p></div>';

    return enviarEmail($pedido['email'], $pedido['nombre'], $asunto, $html);
}

/** Envía un correo con el proveedor configurado. Devuelve false si no se envió. */
function enviarEmail(string $destino, string $nombreDestino, string $asunto, string $html): bool
{
    global $CONFIG;
    $cfg = $CONFIG['email'];

    if (($cfg['proveedor'] ?? 'ninguno') === 'ninguno' || empty($cfg['api_key'])) {
        error_log("Email no enviado (proveedor sin configurar): $asunto -> $destino");
        return false;
    }

    return match ($cfg['proveedor']) {
        'brevo'  => enviarConBrevo($cfg, $destino, $nombreDestino, $asunto, $html),
        'resend' => enviarConResend($cfg, $destino, $asunto, $html),
        default  => false,
    };
}

/** https://developers.brevo.com — plan gratuito: 300 correos/día. */
function enviarConBrevo(array $cfg, string $destino, string $nombre, string $asunto, string $html): bool
{
    return peticionApi(
        'https://api.brevo.com/v3/smtp/email',
        [
            'sender'      => ['email' => $cfg['remitente'], 'name' => $cfg['nombre_envio']],
            'to'          => [['email' => $destino, 'name' => $nombre]],
            'subject'     => $asunto,
            'htmlContent' => $html,
        ],
        ['api-key: ' . $cfg['api_key']]
    );
}

/** https://resend.com — plan gratuito: 100 correos/día. */
function enviarConResend(array $cfg, string $destino, string $asunto, string $html): bool
{
    return peticionApi(
        'https://api.resend.com/emails',
        [
            'from'    => sprintf('%s <%s>', $cfg['nombre_envio'], $cfg['remitente']),
            'to'      => [$destino],
            'subject' => $asunto,
            'html'    => $html,
        ],
        ['Authorization: Bearer ' . $cfg['api_key']]
    );
}

/** POST de JSON contra la API del proveedor. Registra el fallo si lo hay. */
function peticionApi(string $url, array $cuerpo, array $cabecerasExtra): bool
{
    if (!function_exists('curl_init')) {
        error_log('cURL no está disponible en este servidor; no se puede enviar el correo.');
        return false;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($cuerpo, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json', 'Accept: application/json'], $cabecerasExtra),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);

    $respuesta = curl_exec($ch);
    $codigo    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errorCurl = curl_error($ch);
    curl_close($ch);

    if ($codigo >= 200 && $codigo < 300) {
        return true;
    }

    error_log(sprintf('Fallo al enviar email (HTTP %d): %s %s', $codigo, $errorCurl, (string) $respuesta));
    return false;
}
