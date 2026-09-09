<?php
/**
 * Cálculo de métricas de ventas y motor de recomendaciones para el panel
 * de Analítica. "Vendido" se cuenta a partir de pedidos completados o
 * archivados (comida ya servida) — los pendientes/en curso todavía no
 * cuentan como venta, y los cancelados nunca cuentan.
 */

declare(strict_types=1);

require_once __DIR__ . '/arranque.php';

const ANALITICA_ESTADOS_VENDIDOS = ['completado', 'archivado'];

/**
 * Año en que empieza el curso escolar actual (el curso "2026-2027" empieza
 * el 1 de septiembre de 2026). De enero a agosto se sigue contando como
 * parte del curso que empezó el septiembre anterior.
 */
function analiticaAnioInicioCursoActual(): int
{
    $anio = (int) date('Y');
    $mes  = (int) date('n');
    return $mes >= 9 ? $anio : $anio - 1;
}

/** Nombre corto del rango del curso que empieza en $anioInicio (p. ej. "curso_2026"). */
function analiticaValorCurso(int $anioInicio): string
{
    return 'curso_' . $anioInicio;
}

/**
 * Límites [desde, hasta) del rango pedido:
 *   '7' | '30' | '90'   → últimos N días (hasta ahora, sin límite superior)
 *   'todo'               → sin límites
 *   'curso_AAAA'         → curso escolar que empieza el 1 de septiembre de AAAA
 *
 * @return array{0: ?string, 1: ?string}
 */
function analiticaLimitesFecha(string $rango): array
{
    if (preg_match('/^curso_(\d{4})$/', $rango, $coincidencia)) {
        $anioInicio = (int) $coincidencia[1];
        return [
            sprintf('%04d-09-01 00:00:00', $anioInicio),
            sprintf('%04d-09-01 00:00:00', $anioInicio + 1),
        ];
    }

    $dias = match ($rango) {
        '7'  => 7,
        '30' => 30,
        '90' => 90,
        default => null, // 'todo'
    };
    if ($dias === null) {
        return [null, null];
    }
    return [date('Y-m-d 00:00:00', strtotime("-$dias days")), null];
}

/**
 * Cursos escolares con algún pedido registrado, más el curso actual aunque
 * todavía no tenga ninguno. Se usan para pintar los botones de filtro.
 */
function analiticaCursosDisponibles(): array
{
    $anioActual = analiticaAnioInicioCursoActual();

    $primerPedido = bd()->query('SELECT MIN(creado_en) FROM pedidos')->fetchColumn();
    $anioMasAntiguo = $anioActual;
    if ($primerPedido) {
        $mes  = (int) date('n', strtotime($primerPedido));
        $anio = (int) date('Y', strtotime($primerPedido));
        $anioMasAntiguo = $mes >= 9 ? $anio : $anio - 1;
    }

    $cursos = [];
    for ($anio = $anioActual; $anio >= $anioMasAntiguo; $anio--) {
        $cursos[] = [
            'valor'   => analiticaValorCurso($anio),
            'etiqueta' => 'Curso ' . $anio . '-' . ($anio + 1),
            'actual'  => $anio === $anioActual,
        ];
    }
    return $cursos;
}

/** Reúne todas las métricas del rango pedido. */
function analiticaObtenerMetricas(string $rango): array
{
    $pdo = bd();
    [$desde, $hasta] = analiticaLimitesFecha($rango);
    $marcadoresEstado = implode(',', array_fill(0, count(ANALITICA_ESTADOS_VENDIDOS), '?'));

    $condiciones = [];
    $parametrosBase = ANALITICA_ESTADOS_VENDIDOS;
    if ($desde) {
        $condiciones[] = 'pe.creado_en >= ?';
        $parametrosBase[] = $desde;
    }
    if ($hasta) {
        $condiciones[] = 'pe.creado_en < ?';
        $parametrosBase[] = $hasta;
    }
    $condicionFecha = $condiciones ? 'AND ' . implode(' AND ', $condiciones) : '';

    // ---------- Resumen general ----------
    $consulta = $pdo->prepare(
        "SELECT COUNT(*) AS pedidos, COALESCE(SUM(total), 0) AS importe, COALESCE(AVG(total), 0) AS ticket_medio
           FROM pedidos pe
          WHERE pe.estado IN ($marcadoresEstado) $condicionFecha"
    );
    $consulta->execute($parametrosBase);
    $filaResumen = $consulta->fetch();
    $resumen = [
        'pedidos'      => (int) $filaResumen['pedidos'],
        'importe'      => (float) $filaResumen['importe'],
        'ticket_medio' => (float) $filaResumen['ticket_medio'],
    ];

    // ---------- Ranking de productos ----------
    $consulta = $pdo->prepare(
        "SELECT pl.nombre_producto, COALESCE(p.categoria, 'Sin categoría') AS categoria,
                SUM(pl.cantidad) AS unidades,
                SUM(pl.cantidad * pl.precio_unitario) AS importe,
                COUNT(DISTINCT pl.pedido_id) AS pedidos
           FROM pedido_lineas pl
           JOIN pedidos pe ON pe.id = pl.pedido_id
      LEFT JOIN productos p ON p.id = pl.producto_id
          WHERE pe.estado IN ($marcadoresEstado) $condicionFecha
       GROUP BY pl.nombre_producto, categoria
       ORDER BY unidades DESC"
    );
    $consulta->execute($parametrosBase);
    $productos = array_map(static function (array $fila): array {
        return [
            'nombre'    => $fila['nombre_producto'],
            'categoria' => $fila['categoria'],
            'unidades'  => (int) $fila['unidades'],
            'importe'   => (float) $fila['importe'],
            'pedidos'   => (int) $fila['pedidos'],
        ];
    }, $consulta->fetchAll());

    // ---------- Ranking de categorías ----------
    $categorias = [];
    foreach ($productos as $producto) {
        $cat = $producto['categoria'];
        if (!isset($categorias[$cat])) {
            $categorias[$cat] = ['nombre' => $cat, 'unidades' => 0, 'importe' => 0.0];
        }
        $categorias[$cat]['unidades'] += $producto['unidades'];
        $categorias[$cat]['importe']  += $producto['importe'];
    }
    $categorias = array_values($categorias);
    usort($categorias, static fn($a, $b) => $b['importe'] <=> $a['importe']);

    // ---------- Ventas por día (para el gráfico de evolución) ----------
    $consulta = $pdo->prepare(
        "SELECT DATE(pe.creado_en) AS dia, COALESCE(SUM(pe.total), 0) AS importe, COUNT(*) AS pedidos
           FROM pedidos pe
          WHERE pe.estado IN ($marcadoresEstado) $condicionFecha
       GROUP BY DATE(pe.creado_en)
       ORDER BY dia"
    );
    $consulta->execute($parametrosBase);
    $porDia = array_map(static fn($f) => [
        'dia'     => $f['dia'],
        'importe' => (float) $f['importe'],
        'pedidos' => (int) $f['pedidos'],
    ], $consulta->fetchAll());

    // ---------- Ventas por hora del día ----------
    $consulta = $pdo->prepare(
        "SELECT HOUR(pe.creado_en) AS hora, SUM(pl.cantidad) AS unidades
           FROM pedido_lineas pl
           JOIN pedidos pe ON pe.id = pl.pedido_id
          WHERE pe.estado IN ($marcadoresEstado) $condicionFecha
       GROUP BY hora"
    );
    $consulta->execute($parametrosBase);
    $porHora = array_fill(0, 24, 0);
    foreach ($consulta->fetchAll() as $fila) {
        $porHora[(int) $fila['hora']] = (int) $fila['unidades'];
    }

    // ---------- Ventas por día de la semana ----------
    // DAYOFWEEK: 1=domingo ... 7=sábado. Se reordena a lunes..domingo.
    $consulta = $pdo->prepare(
        "SELECT DAYOFWEEK(pe.creado_en) AS dow, COALESCE(SUM(pe.total), 0) AS importe
           FROM pedidos pe
          WHERE pe.estado IN ($marcadoresEstado) $condicionFecha
       GROUP BY dow"
    );
    $consulta->execute($parametrosBase);
    $nombresDia = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
    $importePorIndice = array_fill(0, 7, 0.0);
    foreach ($consulta->fetchAll() as $fila) {
        $indice = ((int) $fila['dow'] + 5) % 7; // domingo(1)->6, lunes(2)->0, ...
        $importePorIndice[$indice] = (float) $fila['importe'];
    }
    $porDiaSemana = [];
    foreach ($nombresDia as $indice => $nombre) {
        $porDiaSemana[] = ['dia' => $nombre, 'importe' => $importePorIndice[$indice]];
    }

    // ---------- Productos activos sin ninguna venta en el rango ----------
    $vendidosNombres = array_column($productos, 'nombre');
    $todosActivos = $pdo->query('SELECT nombre FROM productos WHERE activo = 1')->fetchAll(PDO::FETCH_COLUMN);
    $sinVentas = array_values(array_diff($todosActivos, $vendidosNombres));

    // ---------- Tendencia: primera mitad del rango vs segunda mitad ----------
    $tendencias = analiticaCalcularTendencias($pdo, $rango, $marcadoresEstado);

    return [
        'rango'          => $rango,
        'resumen'        => $resumen,
        'productos'      => $productos,
        'categorias'     => $categorias,
        'por_dia'        => $porDia,
        'por_hora'       => $porHora,
        'por_dia_semana' => $porDiaSemana,
        'sin_ventas'     => $sinVentas,
        'tendencias'     => $tendencias,
    ];
}

/**
 * Compara las unidades vendidas de cada producto entre la primera y la
 * segunda mitad del rango, para detectar quién sube y quién baja.
 * Con rango "todo" se usan los últimos 60 días partidos en dos mitades de 30.
 * Devuelve ['suben' => [...], 'bajan' => [...]], cada uno con como mucho
 * 3 productos, ordenados por magnitud del cambio.
 */
function analiticaCalcularTendencias(PDO $pdo, string $rango, string $marcadoresEstado): array
{
    $diasTotales = match ($rango) {
        '7'  => 7,
        '30' => 30,
        '90' => 90,
        default => 60,
    };
    $mitad  = max(1, (int) floor($diasTotales / 2));
    $inicio = date('Y-m-d 00:00:00', strtotime("-$diasTotales days"));
    $punto  = date('Y-m-d 00:00:00', strtotime("-$mitad days"));

    $sql = "SELECT pl.nombre_producto,
                   SUM(CASE WHEN pe.creado_en < ? THEN pl.cantidad ELSE 0 END) AS antes,
                   SUM(CASE WHEN pe.creado_en >= ? THEN pl.cantidad ELSE 0 END) AS ahora
              FROM pedido_lineas pl
              JOIN pedidos pe ON pe.id = pl.pedido_id
             WHERE pe.estado IN ($marcadoresEstado) AND pe.creado_en >= ?
          GROUP BY pl.nombre_producto";

    $consulta = $pdo->prepare($sql);
    $consulta->execute([$punto, $punto, ...ANALITICA_ESTADOS_VENDIDOS, $inicio]);

    $suben = [];
    $bajan = [];

    foreach ($consulta->fetchAll() as $fila) {
        $antes = (int) $fila['antes'];
        $ahora = (int) $fila['ahora'];

        // Se ignoran productos con muy poco volumen: el % dispara con ruido.
        if ($antes + $ahora < 4) {
            continue;
        }

        if ($antes === 0) {
            $cambio = $ahora > 0 ? 100.0 : 0.0;
        } else {
            $cambio = (($ahora - $antes) / $antes) * 100;
        }

        $item = ['nombre' => $fila['nombre_producto'], 'antes' => $antes, 'ahora' => $ahora, 'cambio' => $cambio];

        if ($cambio >= 25) {
            $suben[] = $item;
        } elseif ($cambio <= -25) {
            $bajan[] = $item;
        }
    }

    usort($suben, static fn($a, $b) => $b['cambio'] <=> $a['cambio']);
    usort($bajan, static fn($a, $b) => $a['cambio'] <=> $b['cambio']);

    return [
        'suben' => array_slice($suben, 0, 3),
        'bajan' => array_slice($bajan, 0, 3),
    ];
}
