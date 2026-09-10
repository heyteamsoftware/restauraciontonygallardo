<?php
/**
 * Stock de ingredientes: pan, embutidos, y cualquier insumo limitado.
 * No se venden directamente, pero se pueden vincular a productos (pestaña
 * Productos) para que su cantidad limite cuántas unidades se pueden vender.
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
  <title>Stock · <?= e($CONFIG['app']['nombre']) ?></title>
  <meta name="robots" content="noindex">
  <link rel="stylesheet" href="../assets/css/estilos.css?v=<?= filemtime(__DIR__ . '/../assets/css/estilos.css') ?>">
</head>
<body class="pagina-panel">

<header class="barra">
  <div class="barra__interior">
    <span class="barra__marca">Cafetería · Stock</span>
    <span class="indicador-conexion" id="indicadorConexion" aria-live="polite"></span>
    <nav class="barra__nav">
      <div class="barra__grupo barra__grupo--operativa">
        <a class="barra__enlace" href="panel.php">Pedidos</a>
        <a class="barra__enlace" href="venta.php">Venta en mostrador</a>
      </div>
      <div class="barra__grupo barra__grupo--gestion">
        <a class="barra__enlace" href="productos.php">Productos</a>
        <a class="barra__enlace barra__enlace--activo" href="stock.php">Stock</a>
        <a class="barra__enlace" href="analitica.php">Analítica</a>
      </div>
      <a class="barra__enlace" href="salir.php">Salir</a>
    </nav>
  </div>
</header>

<main class="panel panel--estrecho">

  <section class="tarjeta">
    <h2 class="tarjeta__titulo">Añadir ingrediente</h2>
    <p class="tarjeta__ayuda">
      Pan de bocadillo, pan de sandwich, lomo, queso... cualquier insumo cuya
      cantidad quieras controlar. Luego, en Productos, marca qué ingredientes
      usa cada producto (y cuántos) para que su stock se calcule solo.
    </p>
    <form class="formulario-linea" id="formularioIngrediente">
      <div class="campo">
        <label for="nombreIngrediente">Nombre</label>
        <input type="text" id="nombreIngrediente" maxlength="100" placeholder="Pan de bocadillo" required>
      </div>
      <div class="campo campo--corto">
        <label for="stockIngrediente">Stock <span class="campo__opcional">(vacío = ilimitado)</span></label>
        <input type="number" id="stockIngrediente" min="0" step="1" placeholder="Ilimitado">
      </div>
      <button class="boton boton--principal" type="submit">Añadir</button>
    </form>
    <p class="campo__error" id="errorIngrediente" hidden></p>
  </section>

  <section class="tarjeta">
    <div class="tarjeta__cabecera">
      <h2 class="tarjeta__titulo">Ingredientes</h2>
      <button class="boton boton--pequeno boton--texto boton--peligro" type="button" id="botonVaciarTodo">
        Vaciar todo el stock
      </button>
    </div>
    <div class="tabla-envoltorio">
      <table class="tabla">
        <thead>
          <tr>
            <th>Ingrediente</th>
            <th class="tabla__derecha">Stock</th>
            <th>Usado en</th>
            <th><span class="visualmente-oculto">Acciones</span></th>
          </tr>
        </thead>
        <tbody id="tablaIngredientes"></tbody>
      </table>
    </div>
    <p class="stock__vacio" id="stockVacio" hidden>Todavía no has añadido ningún ingrediente.</p>
  </section>

</main>

<!-- ============ Editar ingrediente ============ -->
<dialog class="dialogo-producto" id="dialogoEditarIngrediente">
  <form method="dialog" class="dialogo-producto__interior" id="formularioEditarIngrediente">
    <header class="dialogo-producto__cabecera">
      <h2>Editar ingrediente</h2>
      <button class="dialogo-producto__cerrar" type="button" id="botonCerrarEditarIngrediente" aria-label="Cerrar">✕</button>
    </header>

    <div class="dialogo-producto__cuerpo">
      <input type="hidden" id="editarIngredienteId">

      <div class="campo">
        <label for="editarIngredienteNombre">Nombre</label>
        <input type="text" id="editarIngredienteNombre" maxlength="100" required>
      </div>
      <div class="campo campo--corto">
        <label for="editarIngredienteStock">Stock <span class="campo__opcional">(vacío = ilimitado)</span></label>
        <input type="number" id="editarIngredienteStock" min="0" step="1" placeholder="Ilimitado">
      </div>

      <p class="campo__error" id="errorEditarIngrediente" hidden></p>
    </div>

    <footer class="dialogo-producto__pie">
      <button class="boton boton--texto" type="button" id="botonCancelarEditarIngrediente">Cancelar</button>
      <button class="boton boton--principal" type="submit" id="botonGuardarEditarIngrediente">Guardar cambios</button>
    </footer>
  </form>
</dialog>

<script>
  const CSRF = <?= json_encode(tokenCsrf()) ?>;
</script>
<script src="../assets/js/conexion.js?v=<?= filemtime(__DIR__ . '/../assets/js/conexion.js') ?>"></script>
<script src="../assets/js/stock.js?v=<?= filemtime(__DIR__ . '/../assets/js/stock.js') ?>"></script>
</body>
</html>
