<?php
/**
 * Autenticación del panel de administración.
 * Un único usuario, definido en config.php mediante un hash de contraseña.
 */

declare(strict_types=1);

require_once __DIR__ . '/arranque.php';

/** Comprueba usuario y contraseña contra los datos de config.php. */
function credencialesCorrectas(string $usuario, string $password): bool
{
    global $CONFIG;

    $usuarioOk = hash_equals($CONFIG['admin']['usuario'], $usuario);
    $passOk    = password_verify($password, $CONFIG['admin']['hash_pass']);

    // Se comprueban ambas siempre para no filtrar cuál de las dos falló.
    return $usuarioOk && $passOk;
}

function iniciarSesionAdmin(string $usuario): void
{
    iniciarSesion();
    session_regenerate_id(true);          // evita fijación de sesión
    $_SESSION['admin'] = $usuario;
    $_SESSION['admin_desde'] = time();
}

function cerrarSesionAdmin(): void
{
    iniciarSesion();
    $_SESSION = [];
    session_destroy();
}

function hayAdmin(): bool
{
    iniciarSesion();

    if (empty($_SESSION['admin'])) {
        return false;
    }

    // La sesión caduca a las 8 horas (una jornada de instituto).
    if (time() - (int) ($_SESSION['admin_desde'] ?? 0) > 8 * 3600) {
        cerrarSesionAdmin();
        return false;
    }

    return true;
}

/**
 * Corta la ejecución si no hay sesión de administrador.
 * En las peticiones de la API responde JSON; en las páginas, redirige al login.
 */
function exigirAdmin(bool $esApi = false): void
{
    if (hayAdmin()) {
        return;
    }

    if ($esApi) {
        jsonError('Sesión caducada. Vuelve a iniciar sesión.', 401);
    }

    header('Location: index.php');
    exit;
}
