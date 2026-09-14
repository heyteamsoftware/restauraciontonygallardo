<?php
/**
 * Autenticación del panel de administración.
 * Acceso mediante un código numérico de 4 cifras (PIN), definido en
 * config.php como un hash. Pensado para que el personal de la cafetería
 * entre rápido desde una tablet/móvil, sin usuario ni contraseña.
 */

declare(strict_types=1);

require_once __DIR__ . '/arranque.php';

/** Comprueba el PIN de 4 cifras contra el hash guardado en config.php. */
function pinCorrecto(string $pin): bool
{
    global $CONFIG;

    if (!preg_match('/^\d{4}$/', $pin)) {
        return false;
    }

    return password_verify($pin, $CONFIG['admin']['pin_hash']);
}

function iniciarSesionAdmin(): void
{
    iniciarSesion();
    session_regenerate_id(true);          // evita fijación de sesión
    $_SESSION['admin'] = true;
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
