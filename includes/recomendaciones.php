<?php
/**
 * Banco de recomendaciones para la Analítica: 10 categorías x 10 variantes
 * de texto = 100 recomendaciones distintas. Cada categoría se activa solo
 * si los datos reales cumplen su condición (ver analiticaGenerarRecomendaciones
 * más abajo); cuando se activa, se elige al azar una de sus 10 variantes y
 * se rellenan los huecos {placeholder} con los valores reales.
 *
 * Categorías: estrella, flojo, sin_ventas, categoria_dominante,
 * categoria_floja, hora_pico, dia_top, ticket_medio, tendencia, general.
 */

declare(strict_types=1);

function recomendacionesBanco(): array
{
    return [

        // ---------- 1. Producto estrella (más vendido) ----------
        'estrella' => [
            '⭐ "{producto}" es el producto más vendido, con {unidades} unidades. Asegúrate de que nunca falte stock.',
            '⭐ "{producto}" lidera las ventas ({unidades} unidades). Podría ser buena idea destacarlo en el mostrador o en un cartel.',
            '⭐ Con {unidades} unidades vendidas, "{producto}" es vuestro producto de referencia. Vigila que su preparación no se convierta en un cuello de botella en hora punta.',
            '⭐ "{producto}" genera {importe} en ventas, la cifra más alta de todo el catálogo. Merece un hueco preferente en la carta.',
            '⭐ El favorito indiscutible es "{producto}". Podríais preguntaros si hay margen para subir ligeramente su precio sin perder ventas.',
            '⭐ "{producto}" se lleva {unidades} unidades vendidas: muy por delante del resto. Aseguraos de tener siempre ingredientes de sobra.',
            '⭐ Si tuvierais que elegir un producto para una oferta 2x1 puntual como reclamo, "{producto}" no lo necesita: ya vende solo.',
            '⭐ "{producto}" es la prueba de que el catálogo tiene un ganador claro. Usadlo de gancho para dar a conocer otros productos similares.',
            '⭐ Con {pedidos} pedidos distintos que incluyen "{producto}", es el producto más pedido por número de comandas, no solo por unidades.',
            '⭐ "{producto}" domina las ventas. Si algún día falta, tened un sustituto claro pensado para no perder esas ventas.',
        ],

        // ---------- 2. Producto flojo (menos vendido, pero con ventas) ----------
        'flojo' => [
            '📉 "{producto}" es el producto con menos ventas ({unidades} unidades). Puede que no esté bien posicionado en la carta.',
            '📉 Solo {unidades} unidades vendidas de "{producto}". Antes de retirarlo, probad a cambiarle el nombre o la descripción.',
            '📉 "{producto}" apenas se vende. ¿Está claro en la carta lo que lleva? A veces el problema es solo de descripción.',
            '📉 Con {importe} facturados, "{producto}" es el que menos aporta. Valorad si compensa mantenerlo en el catálogo.',
            '📉 "{producto}" vende poco comparado con el resto. Probad a ofrecerlo en combo con el producto estrella para darle visibilidad.',
            '📉 Pocas unidades de "{producto}" ({unidades}). Podría deberse al precio: comparadlo con productos similares de la competencia.',
            '📉 "{producto}" lleva un ritmo de ventas bajo. Antes de descartarlo, probad un cartel o promoción puntual durante una semana.',
            '📉 El producto menos pedido es "{producto}". Si el coste de producirlo es alto para lo poco que se vende, quizá no compensa.',
            '📉 "{producto}" vende poco: puede ser el momento de preguntar directamente a los alumnos por qué no lo eligen.',
            '📉 Con solo {pedidos} pedidos que lo incluyen, "{producto}" pasa desapercibido. Un cambio de foto o de ubicación en la carta puede ayudar.',
        ],

        // ---------- 3. Productos activos sin ninguna venta ----------
        'sin_ventas' => [
            '🚫 "{producto}" no ha tenido ninguna venta en este periodo. Comprobad que aparece bien visible en la web de pedidos.',
            '🚫 Nadie ha pedido "{producto}" todavía. ¿Sigue teniendo sentido tenerlo en el catálogo?',
            '🚫 "{producto}" lleva cero ventas. Antes de retirarlo, aseguraos de que el precio y la descripción son correctos.',
            '🚫 Cero pedidos para "{producto}" en este periodo. Podría ser un buen candidato para una promoción de lanzamiento.',
            '🚫 "{producto}" no se ha vendido nada. Revisad si está activo y visible en la lista de productos.',
            '🚫 Ningún alumno ha probado "{producto}" en este periodo. Un cartel anunciándolo podría cambiar eso.',
            '🚫 "{producto}" sigue a cero ventas. Considerad sustituirlo por algo que sepáis que funciona bien en otros centros.',
            '🚫 Sin ventas de "{producto}": puede que el nombre no resulte atractivo o claro para quien no lo conoce.',
            '🚫 "{producto}" no despega. Preguntad en persona en la cafetería si alguien lo conoce o lo ha probado alguna vez.',
            '🚫 Cero unidades vendidas de "{producto}". Si lleváis más de un mes así, es buena señal para retirarlo del catálogo.',
        ],

        // ---------- 4. Categoría dominante ----------
        'categoria_dominante' => [
            '🏆 La categoría "{categoria}" es la que más factura ({importe}). Es el corazón del negocio ahora mismo.',
            '🏆 "{categoria}" domina las ventas con {unidades} unidades. Merece la pena ampliar la variedad dentro de esta categoría.',
            '🏆 Casi la mitad del negocio pasa por "{categoria}". Cuidad especialmente la calidad y el tiempo de preparación en esta categoría.',
            '🏆 "{categoria}" es vuestra categoría más fuerte. Un producto nuevo dentro de ella tiene más probabilidades de triunfar que en otra.',
            '🏆 Con {importe} facturados, "{categoria}" tira del carro. Aseguraos de que el suministro de ingredientes para esta categoría nunca falla.',
            '🏆 La categoría "{categoria}" concentra buena parte de las ventas. Podría ser el sitio ideal para probar un producto de gama algo más cara.',
            '🏆 "{categoria}" es la categoría preferida del alumnado, con diferencia sobre el resto.',
            '🏆 Si tuvierais que priorizar dónde invertir tiempo mejorando la carta, "{categoria}" es la categoría con más impacto.',
            '🏆 "{categoria}" lidera con {unidades} unidades vendidas. Aprovechad su tirón para promocionar productos nuevos relacionados.',
            '🏆 El grueso del negocio está en "{categoria}". Vale la pena revisar sus precios con más frecuencia que los del resto.',
        ],

        // ---------- 5. Categoría floja ----------
        'categoria_floja' => [
            '🔻 La categoría "{categoria}" es la que menos vende ({importe}). Puede necesitar más variedad o mejor visibilidad.',
            '🔻 "{categoria}" apenas mueve ventas. Comprobad si los productos de esta categoría están bien descritos.',
            '🔻 Con solo {unidades} unidades, "{categoria}" es la categoría más floja. Un producto nuevo y llamativo podría reactivarla.',
            '🔻 "{categoria}" pasa desapercibida. Probad a colocar sus productos más arriba en la carta, donde se ven primero.',
            '🔻 Pocas ventas en "{categoria}" ({importe}). Puede que el precio no esté ajustado a lo que se espera pagar por ella.',
            '🔻 "{categoria}" es la categoría menos popular. Antes de invertir en ampliarla, valorad si de verdad interesa al alumnado.',
            '🔻 La categoría "{categoria}" vende poco comparada con el resto. Un cartel o una oferta puntual puede ayudar a probarla.',
            '🔻 "{categoria}" tiene un rendimiento bajo. Revisad si tiene sentido fusionarla con otra categoría más fuerte.',
            '🔻 Con {unidades} unidades vendidas, "{categoria}" no termina de arrancar. Preguntad directamente qué esperarían encontrar ahí.',
            '🔻 "{categoria}" es la categoría con menos tirón. Si lleva así varias semanas, valorad simplificar la carta quitando alguno de sus productos.',
        ],

        // ---------- 6. Hora punta ----------
        'hora_pico' => [
            '🕐 Las {hora}:00 es la hora con más pedidos. Aseguraos de tener personal suficiente en ese momento.',
            '🕐 La mayoría de la demanda se concentra sobre las {hora}:00. Preparad los productos más pedidos con antelación para esa franja.',
            '🕐 A las {hora}:00 se dispara el número de pedidos. Es el mejor momento para tener el mostrador ya montado y listo.',
            '🕐 La hora punta es a las {hora}:00. Si hay colas, ese es el momento crítico a vigilar.',
            '🕐 Sobre las {hora}:00 se concentra el pico de pedidos del día. Tener los ingredientes ya troceados antes ayuda a ir más rápido.',
            '🕐 Los pedidos se disparan a las {hora}:00. Podría interesar escalonar el horario de recreo si depende del centro.',
            '🕐 La franja de las {hora}:00 es, con diferencia, la más movida. Reforzar el equipo en ese momento reduce las esperas.',
            '🕐 A las {hora}:00 llega el grueso de los pedidos del día. Un segundo punto de recogida en esa franja aliviaría la cola.',
            '🕐 El pico de actividad es a las {hora}:00. Aprovechad las horas más tranquilas para la preparación previa.',
            '🕐 Las {hora}:00 concentran la mayor parte de la demanda diaria. Vale la pena anotar cuánto tarda cada pedido en esa franja para detectar cuellos de botella.',
        ],

        // ---------- 7. Mejor día de la semana ----------
        'dia_top' => [
            '📅 Los {dia} son el día con más ventas de la semana ({importe}). Reforzad el stock ese día.',
            '📅 "{dia}" destaca claramente sobre el resto de días. Podría ser un buen día para probar novedades en el menú.',
            '📅 Las ventas suben notablemente los {dia} ({importe}). Aseguraos de que ese día no falte ningún ingrediente clave.',
            '📅 Los {dia} concentran más facturación que cualquier otro día. Vale la pena preguntarse por qué, y aprovecharlo.',
            '📅 "{dia}" es el día fuerte de la semana. Si vais a hacer alguna promoción, ese día llegará a más gente.',
            '📅 Con {importe} de media, los {dia} superan claramente al resto de la semana.',
            '📅 Las ventas de los {dia} destacan sobre el resto. Comprobad que el personal disponible ese día es suficiente.',
            '📅 "{dia}" es sistemáticamente el día de mayor actividad. Buen día para lanzar un producto nuevo con más gente probándolo.',
            '📅 Los {dia} mueven más dinero que cualquier otro día de la semana. Aseguraos de no quedaros sin cambio en caja ese día.',
            '📅 Si tuvierais que elegir un día para hacer inventario extra de ingredientes, que sea antes del {dia}, el día más fuerte.',
        ],

        // ---------- 8. Ticket medio ----------
        'ticket_medio' => [
            '💶 El ticket medio es de {ticket} €. Un combo de bocata + bebida podría subirlo sin que se note mucho en el precio percibido.',
            '💶 Cada pedido deja de media {ticket} €. Si añadierais un producto de precio algo más alto y llamativo, el ticket medio podría subir.',
            '💶 El gasto medio por pedido es {ticket} €. Vale la pena repasar si los precios reflejan bien el coste real de cada producto.',
            '💶 Con un ticket medio de {ticket} €, hay margen para sugerir "algo más" al hacer el pedido, como un postre o una bebida.',
            '💶 {ticket} € de media por pedido. Comparadlo con el coste medio de producir cada pedido para saber el margen real.',
            '💶 El ticket medio ({ticket} €) es un buen indicador para medir si una promoción futura realmente compensa.',
            '💶 De media, cada alumno gasta {ticket} € por pedido. Si el objetivo es subir ingresos, mejor centrarse en subir esta cifra que en vender más caro.',
            '💶 {ticket} € de ticket medio. Un cartel con "combo del día" suele animar a añadir un producto más al pedido.',
            '💶 El ticket medio actual es {ticket} €. Apuntadlo cada mes para ver si las novedades del menú lo mueven al alza.',
            '💶 Con {ticket} € de media por pedido, podríais fijar un umbral de gasto (por ejemplo redondeando a un número más alto) para regalar algo simbólico.',
        ],

        // ---------- 9. Tendencia (sube o baja) ----------
        'tendencia' => [
            '{icono} "{producto}" {verbo} un {cambio}% últimamente. Merece la pena seguirle la pista.',
            '{icono} Las ventas de "{producto}" {verbo} un {cambio}% respecto a antes. {consejo}',
            '{icono} "{producto}" {verbo} claramente ({cambio}%). {consejo}',
            '{icono} Algo está cambiando con "{producto}": {verbo} un {cambio}% en poco tiempo.',
            '{icono} "{producto}" {verbo} un {cambio}%. Es de las variaciones más marcadas del catálogo ahora mismo.',
            '{icono} El interés por "{producto}" {verbo} un {cambio}% en las últimas semanas. {consejo}',
            '{icono} "{producto}" {verbo} un {cambio}% de un periodo a otro. Vale la pena confirmarlo con un par de semanas más de datos.',
            '{icono} Fijaos en "{producto}": {verbo} un {cambio}%, uno de los movimientos más claros del momento.',
            '{icono} La demanda de "{producto}" {verbo} un {cambio}%. {consejo}',
            '{icono} "{producto}" {verbo} un {cambio}% recientemente. Buen momento para decidir si acompañarlo con una acción concreta.',
        ],

        // ---------- 10. Consejos generales (no dependen de los datos) ----------
        'general' => [
            '💡 Renovar la carta cada cierto tiempo, aunque sea con pequeños cambios, mantiene el interés del alumnado.',
            '💡 Una foto de calidad de los productos más vendidos suele animar a probarlos a quien todavía no los conoce.',
            '💡 Preguntar directamente al alumnado qué echan en falta en la carta es la forma más barata de investigar mercado.',
            '💡 Los productos con opción vegetariana suelen tener buena acogida incluso entre quienes no lo son: no hace falta esconderlos.',
            '💡 Revisar los precios cada trimestre, aunque sea solo para confirmarlos, evita que se queden desfasados sin daros cuenta.',
            '💡 Un cartel sencillo con "lo más pedido de la semana" genera curiosidad y anima a probarlo.',
            '💡 Vigilar el desperdicio de ingredientes por producto ayuda a decidir qué mantener y qué simplificar en la carta.',
            '💡 Ofrecer alguna opción sin gluten, aunque sea puntual, amplía el público que puede pedir sin tener que preguntar antes.',
            '💡 Un pequeño descuento por pedido anticipado (antes de una hora concreta) ayuda a repartir mejor la carga de trabajo.',
            '💡 Comparar precios con cafeterías de institutos cercanos, si se puede, ayuda a saber si estáis por encima o por debajo del mercado.',
        ],
    ];
}

/**
 * Elige una recomendación al azar de la categoría dada, sustituye los
 * {placeholders} por los valores reales y la devuelve junto a su categoría
 * (la usa el panel para colorear cada tarjeta).
 */
function recomendacionElegir(string $categoria, array $valores): ?array
{
    $banco = recomendacionesBanco();
    $variantes = $banco[$categoria] ?? [];
    if (!$variantes) {
        return null;
    }

    $plantilla = $variantes[array_rand($variantes)];
    return ['categoria' => $categoria, 'texto' => strtr($plantilla, $valores)];
}

/**
 * Genera la lista de recomendaciones aplicables a partir de las métricas
 * calculadas por analiticaObtenerMetricas(). Solo se activa una categoría
 * si los datos reales cumplen su condición.
 */
function analiticaGenerarRecomendaciones(array $metricas): array
{
    $recomendaciones = [];
    $euros = static fn(float $n) => number_format($n, 2, ',', '.') . ' €';

    $productos   = $metricas['productos'];
    $categorias  = $metricas['categorias'];
    $porHora     = $metricas['por_hora'];
    $porDiaSemana = $metricas['por_dia_semana'];
    $sinVentas   = $metricas['sin_ventas'];
    $tendencias  = $metricas['tendencias'];
    $resumen     = $metricas['resumen'];

    // 1. Estrella
    if ($productos) {
        $p = $productos[0];
        $recomendaciones[] = recomendacionElegir('estrella', [
            '{producto}' => $p['nombre'],
            '{unidades}' => (string) $p['unidades'],
            '{importe}'  => $euros($p['importe']),
            '{pedidos}'  => (string) $p['pedidos'],
        ]);
    }

    // 2. Flojo (solo si hay más de un producto, para no repetir el mismo)
    if (count($productos) >= 2) {
        $p = end($productos);
        $recomendaciones[] = recomendacionElegir('flojo', [
            '{producto}' => $p['nombre'],
            '{unidades}' => (string) $p['unidades'],
            '{importe}'  => $euros($p['importe']),
            '{pedidos}'  => (string) $p['pedidos'],
        ]);
    }

    // 3. Sin ventas (hasta 2, para no saturar)
    foreach (array_slice($sinVentas, 0, 2) as $nombre) {
        $recomendaciones[] = recomendacionElegir('sin_ventas', ['{producto}' => $nombre]);
    }

    // 4. Categoría dominante
    if ($categorias) {
        $c = $categorias[0];
        $recomendaciones[] = recomendacionElegir('categoria_dominante', [
            '{categoria}' => $c['nombre'],
            '{unidades}'  => (string) $c['unidades'],
            '{importe}'   => $euros($c['importe']),
        ]);
    }

    // 5. Categoría floja
    if (count($categorias) >= 2) {
        $c = end($categorias);
        $recomendaciones[] = recomendacionElegir('categoria_floja', [
            '{categoria}' => $c['nombre'],
            '{unidades}'  => (string) $c['unidades'],
            '{importe}'   => $euros($c['importe']),
        ]);
    }

    // 6. Hora punta
    $horaPico = array_keys($porHora, max($porHora))[0] ?? null;
    if ($horaPico !== null && max($porHora) > 0) {
        $recomendaciones[] = recomendacionElegir('hora_pico', ['{hora}' => (string) $horaPico]);
    }

    // 7. Mejor día de la semana
    $importesDia = array_column($porDiaSemana, 'importe');
    if ($importesDia && max($importesDia) > 0) {
        $indice = array_keys($importesDia, max($importesDia))[0];
        $recomendaciones[] = recomendacionElegir('dia_top', [
            '{dia}'     => $porDiaSemana[$indice]['dia'],
            '{importe}' => $euros($porDiaSemana[$indice]['importe']),
        ]);
    }

    // 8. Ticket medio
    if ($resumen['pedidos'] > 0) {
        $recomendaciones[] = recomendacionElegir('ticket_medio', ['{ticket}' => number_format($resumen['ticket_medio'], 2, ',', '.')]);
    }

    // 9. Tendencias (hasta 2 subidas y 2 bajadas)
    foreach (array_slice($tendencias['suben'], 0, 2) as $item) {
        $recomendaciones[] = recomendacionElegir('tendencia', [
            '{producto}' => $item['nombre'],
            '{cambio}'   => number_format(abs($item['cambio']), 0),
            '{icono}'    => '📈',
            '{verbo}'    => 'ha subido',
            '{consejo}'  => 'Buen momento para asegurar que no falta stock.',
        ]);
    }
    foreach (array_slice($tendencias['bajan'], 0, 2) as $item) {
        $recomendaciones[] = recomendacionElegir('tendencia', [
            '{producto}' => $item['nombre'],
            '{cambio}'   => number_format(abs($item['cambio']), 0),
            '{icono}'    => '📉',
            '{verbo}'    => 'ha bajado',
            '{consejo}'  => 'Puede valer la pena preguntarse qué ha cambiado.',
        ]);
    }

    // 10. Consejos generales (2 distintos, para que siempre haya contenido)
    $bancoGeneral = recomendacionesBanco()['general'];
    $indices = array_rand($bancoGeneral, min(2, count($bancoGeneral)));
    foreach ((array) $indices as $indice) {
        $recomendaciones[] = ['categoria' => 'general', 'texto' => $bancoGeneral[$indice]];
    }

    return array_values(array_filter($recomendaciones));
}
