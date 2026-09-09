<?php
/**
 * UTILIDAD DE INSTALACIÓN — BORRAR DEL SERVIDOR DESPUÉS DE USARLA.
 *
 * Genera el hash de la contraseña del panel para pegarlo en config.php.
 * Uso:  https://tu-web/admin/generar_hash.php?clave=laquequieras
 */

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$clave = $_GET['clave'] ?? '';

if (strlen($clave) < 8) {
    exit("Añade ?clave=... a la URL, con al menos 8 caracteres.\n");
}

echo password_hash($clave, PASSWORD_DEFAULT), "\n\n";
echo "Copia esa línea en includes/config.php (admin > hash_pass)\n";
echo "y BORRA este archivo del servidor.\n";
