<?php
/**
 * UTILIDAD DE INSTALACIÓN — BORRAR DEL SERVIDOR DESPUÉS DE USARLA.
 *
 * Genera el hash del código de acceso (4 cifras) para pegarlo en config.php.
 * Uso:  https://tu-web/admin/generar_hash.php?pin=1234
 */

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$pin = (string) ($_GET['pin'] ?? '');

if (!preg_match('/^\d{4}$/', $pin)) {
    exit("Añade ?pin=1234 a la URL, con exactamente 4 cifras.\n");
}

echo password_hash($pin, PASSWORD_DEFAULT), "\n\n";
echo "Copia esa línea en includes/config.php (admin > pin_hash)\n";
echo "y BORRA este archivo del servidor.\n";
