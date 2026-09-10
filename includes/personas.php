<?php
/**
 * Verificación de DNI/NIE contra el listado interno de alumnado y personal
 * (includes/personas_autorizadas.php, nunca publicado ni subido a git).
 */

declare(strict_types=1);

/** Deja el DNI/NIE en mayúsculas y sin espacios ni guiones. */
function normalizarDni(string $dni): string
{
    return strtoupper(preg_replace('/[^0-9A-Z]/i', '', $dni) ?? '');
}

/**
 * Busca un DNI/NIE en el listado autorizado.
 * Devuelve ['nombre' => ..., 'apellido' => ..., 'tipo' => 'alumno'|'profesor'] o null.
 */
function personaAutorizada(string $dni): ?array
{
    static $lista = null;
    if ($lista === null) {
        $ruta = __DIR__ . '/personas_autorizadas.php';
        $lista = is_file($ruta) ? require $ruta : [];
    }

    $clave = normalizarDni($dni);
    if (!isset($lista[$clave])) {
        return null;
    }

    [$nombre, $apellido, $tipo] = $lista[$clave];
    return ['nombre' => $nombre, 'apellido' => $apellido, 'tipo' => $tipo];
}

/** Nombre y apellido juntos, tal como se muestran en la confirmación del pedido. */
function nombreCompleto(array $persona): string
{
    return trim($persona['nombre'] . ' ' . $persona['apellido']);
}
