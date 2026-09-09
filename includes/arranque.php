<?php
/**
 * Arranque común: carga la configuración, abre la conexión a la base de
 * datos y define las utilidades que usan todas las páginas.
 * Se incluye siempre con:  require_once __DIR__ . '/../includes/arranque.php';
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
//  Configuración
// ---------------------------------------------------------------------
$rutaConfig = __DIR__ . '/config.php';
if (!is_file($rutaConfig)) {
    http_response_code(500);
    exit('Falta includes/config.php. Copia includes/config.ejemplo.php y rellena los datos.');
}

/** @var array $CONFIG */
$CONFIG = require $rutaConfig;

date_default_timezone_set($CONFIG['app']['zona_horaria'] ?? 'Europe/Madrid');

// En producción no se muestran errores al visitante, pero sí se registran.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// ---------------------------------------------------------------------
//  Base de datos
// ---------------------------------------------------------------------
function bd(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    global $CONFIG;
    $c = $CONFIG['bd'];
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $c['host'], $c['nombre']);

    try {
        $pdo = new PDO($dsn, $c['usuario'], $c['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('Error de conexión a la BD: ' . $e->getMessage());
        http_response_code(500);
        exit('No se puede conectar con la base de datos. Inténtalo más tarde.');
    }

    return $pdo;
}

// ---------------------------------------------------------------------
//  Utilidades
// ---------------------------------------------------------------------

/** Escapa texto para imprimirlo en HTML sin riesgo de inyección. */
function e(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Devuelve una respuesta JSON y termina la ejecución. */
function json(array $datos, int $codigo = 200): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Atajo para devolver un error en formato JSON. */
function jsonError(string $mensaje, int $codigo = 400): never
{
    json(['ok' => false, 'error' => $mensaje], $codigo);
}

/** Lee el cuerpo JSON de una petición. Devuelve [] si no es válido. */
function cuerpoJson(): array
{
    $crudo = file_get_contents('php://input') ?: '';
    $datos = json_decode($crudo, true);
    return is_array($datos) ? $datos : [];
}

/** Genera el código corto que se le enseña al alumno: 4 números y 1 letra (p. ej. "1234A"). */
function generarCodigo(): string
{
    // Sin O ni I, para no confundirlas con 0 y 1 al leerlas en voz alta.
    $letras = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $numero = random_int(0, 9999);
    $letra  = $letras[random_int(0, strlen($letras) - 1)];
    return sprintf('%04d%s', $numero, $letra);
}

/** ¿Estamos dentro del horario en el que se aceptan pedidos? */
function dentroDeHorario(): bool
{
    global $CONFIG;
    [$desde, $hasta] = $CONFIG['app']['horario_pedidos'] ?? [0, 24];
    $hora = (int) date('G');
    return $hora >= $desde && $hora < $hasta;
}

// ---------------------------------------------------------------------
//  Protección CSRF (para el panel de administración)
// ---------------------------------------------------------------------
function iniciarSesion(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
        session_start();
    }
}

function tokenCsrf(): string
{
    iniciarSesion();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function comprobarCsrf(?string $token): bool
{
    iniciarSesion();
    return is_string($token)
        && !empty($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}
