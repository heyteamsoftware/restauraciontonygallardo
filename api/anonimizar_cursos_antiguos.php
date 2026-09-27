<?php
/**
 * POST api/anonimizar_cursos_antiguos.php
 * Aplica la política de privacidad publicada en index.php: borra el
 * nombre y el correo de los pedidos de cursos escolares ya terminados
 * (todo lo anterior al 1 de septiembre del curso actual), dejando el
 * resto del pedido (código, productos, importe) intacto para la
 * analítica histórica.
 *
 * Se ejecuta a mano desde el panel de Analítica porque este servidor
 * no tiene cron disponible para hacerlo automáticamente cada curso.
 *
 * Cuerpo (JSON): { csrf }
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/analitica.php';

exigirAdmin(esApi: true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido.', 405);
}

$datos = cuerpoJson();

if (!comprobarCsrf($datos['csrf'] ?? null)) {
    jsonError('Petición no válida. Recarga la página.', 403);
}

$inicioCursoActual = sprintf('%d-09-01 00:00:00', analiticaAnioInicioCursoActual());

$consulta = bd()->prepare(
    "UPDATE pedidos
        SET nombre = 'Curso anterior', email = ''
      WHERE creado_en < ?
        AND nombre <> 'Curso anterior'"
);
$consulta->execute([$inicioCursoActual]);

json(['ok' => true, 'anonimizados' => $consulta->rowCount()]);
