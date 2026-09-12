<?php
/**
 * Identificación del alumnado/personal para poder pedir, SIN usar ningún
 * documento de identidad oficial (DNI/NIE) en ningún punto de la app: ni
 * en el formulario, ni en lo que viaja al servidor, ni en lo que se
 * guarda en la base de datos de pedidos, ni en el listado interno del
 * propio servidor (includes/personas_autorizadas.php, nunca publicado ni
 * subido a git).
 *
 * Cada persona autorizada tiene solo un ID interno arbitrario (un
 * correlativo sin ningún significado) y su nombre. El alumno busca su
 * nombre en un desplegable y elige el suyo; lo que llega al servidor y lo
 * que se guarda del pedido es ese ID, nunca un dato de identidad oficial.
 */

declare(strict_types=1);

/** Quita acentos y pasa a minúsculas, para poder comparar nombres al buscar. */
function normalizarTexto(string $texto): string
{
    $texto = mb_strtolower($texto, 'UTF-8');
    $con    = ['á','é','í','ó','ú','ü','ñ'];
    $sin    = ['a','e','i','o','u','u','n'];
    return str_replace($con, $sin, $texto);
}

/** Listado completo (id interno => [nombre, apellido, tipo]), cargado una sola vez. */
function listaPersonasAutorizadas(): array
{
    static $lista = null;
    if ($lista === null) {
        $ruta = __DIR__ . '/personas_autorizadas.php';
        $lista = is_file($ruta) ? require $ruta : [];
    }
    return $lista;
}

/**
 * Busca personas autorizadas por nombre para el desplegable del
 * formulario. Devuelve como mucho $limite resultados, con las
 * coincidencias que empiezan igual que la búsqueda primero.
 *
 * @return array<int, array{id:string, nombre_completo:string}>
 */
function buscarPersonas(string $consulta, int $limite = 8): array
{
    $consulta = trim($consulta);
    if (mb_strlen($consulta) < 2) {
        return [];
    }
    $consultaNormalizada = normalizarTexto($consulta);

    $empiezan = [];
    $contienen = [];

    foreach (listaPersonasAutorizadas() as $id => [$nombre, $apellido]) {
        $nombreCompleto = trim($nombre . ' ' . $apellido);
        $normalizado = normalizarTexto($nombreCompleto);

        if (str_starts_with($normalizado, $consultaNormalizada)) {
            $empiezan[] = ['id' => (string) $id, 'nombre_completo' => $nombreCompleto];
        } elseif (str_contains($normalizado, $consultaNormalizada)) {
            $contienen[] = ['id' => (string) $id, 'nombre_completo' => $nombreCompleto];
        }
    }

    $resultado = array_merge($empiezan, $contienen);
    usort($resultado, static fn($a, $b) => strcmp($a['nombre_completo'], $b['nombre_completo']));

    return array_slice($resultado, 0, $limite);
}

/**
 * Recupera nombre/apellido/tipo a partir del ID interno que envía el
 * formulario. Devuelve null si no corresponde a nadie del listado.
 */
function personaPorId(string $id): ?array
{
    $lista = listaPersonasAutorizadas();
    if (!isset($lista[$id])) {
        return null;
    }
    [$nombre, $apellido, $tipo] = $lista[$id];
    return ['nombre' => $nombre, 'apellido' => $apellido, 'tipo' => $tipo];
}

/** Nombre y apellido juntos, tal como se muestran en la confirmación del pedido. */
function nombreCompleto(array $persona): string
{
    return trim($persona['nombre'] . ' ' . $persona['apellido']);
}
