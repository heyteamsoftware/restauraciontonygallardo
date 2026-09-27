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

/** IP del visitante (para el bloqueo por intentos fallidos). */
function ipVisitante(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'desconocida'), 0, 45);
}

/**
 * Bloqueo por fuerza bruta del PIN, por IP: a partir de 5 intentos
 * fallidos seguidos, se bloquea con una espera que crece cada vez
 * (5 min, 10, 20... hasta un tope de 24h). Se guarda en la tabla
 * intentos_pin (ver sql/esquema.sql) en vez de en la sesión, porque un
 * ataque real no manda cookies entre peticiones.
 *
 * @return int Minutos que quedan de bloqueo (0 si no está bloqueada).
 */
function minutosBloqueoRestantes(): int
{
    // La resta se hace en el propio SQL (con NOW(), en la zona horaria del
    // servidor de base de datos) en vez de con strtotime()/time() en PHP:
    // date_default_timezone_set() cambia cómo PHP interpreta la fecha leída
    // y desincroniza esa comparación con la hora real.
    $ip = ipVisitante();
    $consulta = bd()->prepare(
        'SELECT GREATEST(0, CEIL(TIMESTAMPDIFF(SECOND, NOW(), bloqueado_hasta) / 60))
           FROM intentos_pin WHERE ip = ?'
    );
    $consulta->execute([$ip]);
    $minutos = $consulta->fetchColumn();

    return $minutos !== false ? (int) $minutos : 0;
}

/** Registra un intento fallido y calcula si toca bloquear (y cuánto tiempo). */
function registrarIntentoFallido(): void
{
    $ip  = ipVisitante();
    $pdo = bd();

    $pdo->prepare(
        'INSERT INTO intentos_pin (ip, intentos, actualizado_en)
         VALUES (?, 1, NOW())
         ON DUPLICATE KEY UPDATE intentos = intentos + 1, actualizado_en = NOW()'
    )->execute([$ip]);

    $consulta = $pdo->prepare('SELECT intentos FROM intentos_pin WHERE ip = ?');
    $consulta->execute([$ip]);
    $intentos = (int) $consulta->fetchColumn();

    if ($intentos < 5) {
        return; // aún no toca bloquear
    }

    // 5 intentos -> 5 min; cada intento de más dobla la espera, hasta 24h.
    $minutos = min(5 * 2 ** ($intentos - 5), 24 * 60);
    $pdo->prepare('UPDATE intentos_pin SET bloqueado_hasta = NOW() + INTERVAL ? MINUTE WHERE ip = ?')
        ->execute([$minutos, $ip]);
}

/** Se llama al acertar el PIN: olvida los intentos fallidos previos de esta IP. */
function limpiarIntentosFallidos(): void
{
    bd()->prepare('DELETE FROM intentos_pin WHERE ip = ?')->execute([ipVisitante()]);
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
