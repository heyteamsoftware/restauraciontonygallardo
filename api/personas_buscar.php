<?php
/**
 * GET api/personas_buscar.php?q=texto
 * Autocompletar de nombres para el formulario de pedido: busca en el
 * listado interno de alumnado/personal por nombre y devuelve como mucho
 * 8 coincidencias. En NINGÚN caso se expone aquí (ni en ningún otro
 * punto de la app) un documento de identidad: solo nombre + un ID
 * interno arbitrario que el propio formulario usará para decir "soy
 * esta persona de la lista" al crear el pedido.
 *
 * Respuesta: { ok: true, personas: [{ id, nombre_completo }, ...] }
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/arranque.php';
require_once __DIR__ . '/../includes/personas.php';

$consulta = (string) ($_GET['q'] ?? '');

json(['ok' => true, 'personas' => buscarPersonas($consulta)]);
