<?php
/**
 * Venta en mostrador: para el personal que atiende en persona en la
 * cafetería. Crea pedidos manualmente sin pasar por el móvil del cliente;
 * entran en el mismo flujo (pendiente → en_curso → completado) que los
 * pedidos normales. Interfaz pensada para pantalla grande, con botones
 * más cómodos que la versión del alumnado.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/iconos_productos.php';

exigirAdmin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Venta en mostrador · <?= e($CONFIG['app']['nombre']) ?></title>
  <meta name="robots" content="noindex">
  <link rel="stylesheet" href="../assets/css/estilos.css?v=<?= filemtime(__DIR__ . '/../assets/css/estilos.css') ?>">
</head>
<body class="pagina-panel">

<header class="barra">
  <div class="barra__interior">
    <span class="barra__marca">Cafetería · Venta en mostrador</span>
    <span class="indicador-conexion" id="indicadorConexion" aria-live="polite"></span>
    <nav class="barra__nav">
      <div class="barra__grupo barra__grupo--operativa">
        <a class="barra__enlace" href="panel.php">Pedidos</a>
        <a class="barra__enlace barra__enlace--activo" href="venta.php">Venta en mostrador</a>
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

<main class="panel panel--venta">

  <p class="venta__vacio" id="ventaCargando">Cargando productos…</p>

  <div id="ventaContenido" hidden>
    <div class="venta__catalogo" id="ventaCatalogo"></div>
  </div>

  <div class="venta__barra" id="ventaBarra" hidden>
    <div class="venta__cesta" id="ventaCesta"></div>
    <div class="venta__resumen">
      <span id="ventaUnidades">0 productos</span>
      <strong id="ventaTotal">0,00 €</strong>
    </div>
    <input type="text" id="ventaNombre" class="venta__nombre" maxlength="120"
           placeholder="Nombre para el ticket (opcional)">
    <button class="boton boton--texto" type="button" id="botonVaciar">Vaciar</button>
    <button class="boton boton--principal venta__boton-cobrar" type="button" id="botonCobrar" disabled>
      Crear pedido
    </button>
  </div>

  <dialog class="dialogo-confirmacion" id="dialogoConfirmacion">
    <div class="dialogo-confirmacion__interior">
      <div class="confirmacion__icono" aria-hidden="true">✓</div>
      <h2>Pedido creado</h2>
      <p class="confirmacion__codigo" id="ventaCodigo"></p>
      <button class="boton boton--principal" type="button" id="botonNuevaVenta">Nueva venta</button>
    </div>
  </dialog>

</main>

<script>
  const CSRF = <?= json_encode(tokenCsrf()) ?>;
  const VERSION_ICONOS = <?= json_encode(versionIconos()) ?>;
</script>
<script src="../assets/js/conexion.js?v=<?= filemtime(__DIR__ . '/../assets/js/conexion.js') ?>"></script>
<script src="../assets/js/venta.js?v=<?= filemtime(__DIR__ . '/../assets/js/venta.js') ?>"></script>
</body>
</html>
