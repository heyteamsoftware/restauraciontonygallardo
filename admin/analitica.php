<?php
/**
 * Analítica: métricas de ventas, gráficos y recomendaciones.
 * Los datos se cargan desde api/analitica.php (assets/js/analitica.js).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

exigirAdmin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Analítica · <?= e($CONFIG['app']['nombre']) ?></title>
  <meta name="robots" content="noindex">
  <link rel="stylesheet" href="../assets/css/estilos.css?v=<?= filemtime(__DIR__ . '/../assets/css/estilos.css') ?>">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.5.1/chart.umd.min.js"></script>
</head>
<body class="pagina-panel">

<header class="barra">
  <div class="barra__interior">
    <span class="barra__marca">Cafetería · Analítica</span>
    <span class="indicador-conexion" id="indicadorConexion" aria-live="polite"></span>
    <nav class="barra__nav">
      <a class="barra__enlace" href="panel.php">Pedidos</a>
      <a class="barra__enlace" href="productos.php">Productos</a>
      <a class="barra__enlace barra__enlace--activo" href="analitica.php">Analítica</a>
      <a class="barra__enlace" href="salir.php">Salir</a>
    </nav>
  </div>
</header>

<main class="panel panel--analitica">

  <div class="analitica__controles">
    <div class="filtros-rango" id="filtrosRango" role="group" aria-label="Rango de fechas">
      <button class="filtro-rango" data-rango="7">7 días</button>
      <button class="filtro-rango" data-rango="30">30 días</button>
      <button class="filtro-rango" data-rango="90">90 días</button>
      <button class="filtro-rango" data-rango="todo">Todo</button>
    </div>
  </div>

  <p class="analitica__cargando" id="analiticaCargando">Cargando datos…</p>
  <p class="analitica__vacio" id="analiticaVacio" hidden>
    Todavía no hay pedidos completados en este rango. En cuanto haya ventas, aquí aparecerán las estadísticas y recomendaciones.
  </p>

  <div id="analiticaContenido" hidden>

    <!-- ---------- Resumen ---------- -->
    <section class="tarjetas-resumen" id="tarjetasResumen">
      <div class="tarjeta-resumen">
        <span class="tarjeta-resumen__etiqueta">Pedidos vendidos</span>
        <span class="tarjeta-resumen__valor" id="valorPedidos">0</span>
      </div>
      <div class="tarjeta-resumen">
        <span class="tarjeta-resumen__etiqueta">Facturación</span>
        <span class="tarjeta-resumen__valor" id="valorImporte">0,00 €</span>
      </div>
      <div class="tarjeta-resumen">
        <span class="tarjeta-resumen__etiqueta">Ticket medio</span>
        <span class="tarjeta-resumen__valor" id="valorTicket">0,00 €</span>
      </div>
      <div class="tarjeta-resumen">
        <span class="tarjeta-resumen__etiqueta">Producto estrella</span>
        <span class="tarjeta-resumen__valor tarjeta-resumen__valor--texto" id="valorEstrella">—</span>
      </div>
    </section>

    <!-- ---------- Gráficos ---------- -->
    <section class="bloque-analitica">
      <h2 class="bloque-analitica__titulo">Evolución de ventas</h2>
      <div class="grafico-envoltorio grafico-envoltorio--ancho">
        <canvas id="graficoEvolucion"></canvas>
      </div>
    </section>

    <section class="rejilla-graficos">
      <div class="bloque-analitica">
        <h2 class="bloque-analitica__titulo">Productos más vendidos</h2>
        <div class="grafico-envoltorio">
          <canvas id="graficoProductos"></canvas>
        </div>
      </div>

      <div class="bloque-analitica">
        <h2 class="bloque-analitica__titulo">Ventas por categoría</h2>
        <div class="grafico-envoltorio">
          <canvas id="graficoCategorias"></canvas>
        </div>
      </div>

      <div class="bloque-analitica">
        <h2 class="bloque-analitica__titulo">Ventas por hora del día</h2>
        <div class="grafico-envoltorio">
          <canvas id="graficoHoras"></canvas>
        </div>
      </div>

      <div class="bloque-analitica">
        <h2 class="bloque-analitica__titulo">Ventas por día de la semana</h2>
        <div class="grafico-envoltorio">
          <canvas id="graficoDiaSemana"></canvas>
        </div>
      </div>
    </section>

    <!-- ---------- Tabla detallada ---------- -->
    <section class="bloque-analitica">
      <h2 class="bloque-analitica__titulo">Detalle por producto</h2>
      <div class="tabla-envoltorio">
        <table class="tabla">
          <thead>
            <tr>
              <th>Producto</th>
              <th>Categoría</th>
              <th class="tabla__derecha">Unidades</th>
              <th class="tabla__derecha">Pedidos</th>
              <th class="tabla__derecha">Facturación</th>
            </tr>
          </thead>
          <tbody id="tablaDetalle"></tbody>
        </table>
      </div>
    </section>

    <!-- ---------- Recomendaciones ---------- -->
    <section class="bloque-analitica">
      <h2 class="bloque-analitica__titulo">Recomendaciones</h2>
      <div class="recomendaciones" id="recomendaciones"></div>
    </section>

  </div>
</main>

<script src="../assets/js/conexion.js?v=<?= filemtime(__DIR__ . '/../assets/js/conexion.js') ?>"></script>
<script src="../assets/js/analitica.js?v=<?= filemtime(__DIR__ . '/../assets/js/analitica.js') ?>"></script>
</body>
</html>
