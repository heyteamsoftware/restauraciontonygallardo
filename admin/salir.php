<?php
/** Cierra la sesión del panel. */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

cerrarSesionAdmin();
header('Location: index.php');
exit;
