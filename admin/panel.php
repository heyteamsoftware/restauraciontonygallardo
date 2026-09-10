<?php
/**
 * Panel de pedidos en tiempo real.
 * Los pedidos se cargan y refrescan desde assets/js/panel.js.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

exigirAdmin();

$fecha = (string) ($_GET['fecha'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    $fecha = date('Y-m-d');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pedidos · <?= e($CONFIG['app']['nombre']) ?></title>
  <meta name="robots" content="noindex">
  <link rel="stylesheet" href="../assets/css/estilos.css?v=<?= filemtime(__DIR__ . '/../assets/css/estilos.css') ?>">
</head>
<body class="pagina-panel">

<header class="barra">
  <div class="barra__interior">
    <span class="barra__marca">Cafetería · Pedidos</span>
    <nav class="barra__nav">
      <div class="barra__grupo barra__grupo--operativa">
        <a class="barra__enlace barra__enlace--activo" href="panel.php">Pedidos</a>
        <a class="barra__enlace" href="venta.php">Venta en mostrador</a>
      </div>
      <div class="barra__grupo barra__grupo--gestion">
        <a class="barra__enlace" href="productos.php">Productos</a>
        <a class="barra__enlace" href="stock.php">Stock</a>
        <a class="barra__enlace" href="analitica.php">Analítica</a>
      </div>
      <a class="barra__enlace" href="salir.php">Salir</a>
    </nav>
  </div>
</header>

<main class="panel">

  <div class="panel__controles">
    <div class="campo campo--enlinea">
      <label for="fecha">Día</label>
      <input type="date" id="fecha" value="<?= e($fecha) ?>">
    </div>

    <div class="contadores" id="contadores">
      <span class="contador-estado contador-estado--pendiente">Pendientes <b id="cuentaPendiente">0</b></span>
      <span class="contador-estado contador-estado--en_curso">En curso <b id="cuentaEnCurso">0</b></span>
      <span class="contador-estado contador-estado--completado">Completados <b id="cuentaCompletado">0</b></span>
      <span class="contador-estado contador-estado--archivado">Archivados <b id="cuentaArchivado">0</b></span>
    </div>

    <label class="interruptor">
      <input type="checkbox" id="sonido" checked>
      <span>Avisar con un sonido</span>
    </label>

    <span class="estado-conexion" id="estadoConexion" aria-live="polite">Conectando…</span>
  </div>

  <div class="tablero">
    <section class="columna" data-estado="pendiente">
      <h2 class="columna__titulo">Pendientes</h2>
      <div class="columna__lista" id="col-pendiente"></div>
    </section>

    <section class="columna" data-estado="en_curso">
      <h2 class="columna__titulo">En curso</h2>
      <div class="columna__lista" id="col-en_curso"></div>
    </section>

    <section class="columna" data-estado="completado">
      <h2 class="columna__titulo">Completados</h2>
      <div class="columna__lista" id="col-completado"></div>
    </section>

    <details class="columna columna--archivado" data-estado="archivado" id="columnaArchivado">
      <summary class="columna__titulo">Archivados <span class="columna__contador" id="cuentaArchivadoColumna">0</span></summary>
      <p class="columna__ayuda">Un pedido completado pasa aquí solo a las 6 horas.</p>
      <div class="columna__lista" id="col-archivado"></div>
    </details>
  </div>

  <p class="panel__vacio" id="mensajeVacio" hidden>Todavía no hay pedidos este día.</p>
</main>

<script>
  // Datos que el JavaScript necesita del servidor.
  const CSRF = <?= json_encode(tokenCsrf()) ?>;
  const FECHA_INICIAL = <?= json_encode($fecha) ?>;

  // Para registrar los pedidos completados en Google Sheets. Se llama
  // directamente desde este navegador (ver assets/js/panel.js): el
  // servidor no puede alcanzar script.google.com desde InfinityFree.
  const HOJA_WEBHOOK  = <?= json_encode($CONFIG['hoja']['webhook'] ?? '') ?>;
  const HOJA_PASSWORD = <?= json_encode($CONFIG['hoja']['password'] ?? '') ?>;
</script>
<script src="../assets/js/panel.js?v=<?= filemtime(__DIR__ . '/../assets/js/panel.js') ?>"></script>
</body>
</html>
