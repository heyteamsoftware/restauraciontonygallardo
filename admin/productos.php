<?php
/**
 * Gestión del catálogo: alta, edición, activación y borrado de productos.
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
  <title>Productos · <?= e($CONFIG['app']['nombre']) ?></title>
  <meta name="robots" content="noindex">
  <link rel="stylesheet" href="../assets/css/estilos.css?v=<?= filemtime(__DIR__ . '/../assets/css/estilos.css') ?>">
</head>
<body class="pagina-panel">

<header class="barra">
  <div class="barra__interior">
    <span class="barra__marca">Cafetería · Productos</span>
    <span class="indicador-conexion" id="indicadorConexion" aria-live="polite"></span>
    <nav class="barra__nav">
      <a class="barra__enlace" href="panel.php">Pedidos</a>
      <a class="barra__enlace barra__enlace--activo" href="productos.php">Productos</a>
      <a class="barra__enlace" href="salir.php">Salir</a>
    </nav>
  </div>
</header>

<main class="panel panel--estrecho">

  <section class="tarjeta">
    <h2 class="tarjeta__titulo">Añadir producto</h2>
    <form class="formulario-linea" id="formularioProducto">
      <div class="campo">
        <label for="nombreProducto">Nombre</label>
        <input type="text" id="nombreProducto" maxlength="100" placeholder="Bocata de tortilla" required>
      </div>
      <div class="campo">
        <label for="categoriaProducto">Categoría</label>
        <input type="text" id="categoriaProducto" maxlength="50" list="categorias"
               placeholder="Bocatas" required>
        <datalist id="categorias"></datalist>
      </div>
      <div class="campo campo--corto">
        <label for="precioProducto">Precio (€)</label>
        <input type="number" id="precioProducto" min="0" max="999.99" step="0.05" value="0.00" required>
      </div>
      <button class="boton boton--principal" type="submit">Añadir</button>
    </form>
    <p class="campo__error" id="errorProducto" hidden></p>
  </section>

  <section class="tarjeta">
    <h2 class="tarjeta__titulo">Catálogo</h2>
    <p class="tarjeta__ayuda">
      Los productos desactivados no aparecen en la web de los alumnos, pero se
      conservan en los pedidos ya hechos.
    </p>
    <div class="tabla-envoltorio">
      <table class="tabla">
        <thead>
          <tr>
            <th>Producto</th>
            <th>Categoría</th>
            <th class="tabla__derecha">Precio</th>
            <th>Visible</th>
            <th><span class="visualmente-oculto">Acciones</span></th>
          </tr>
        </thead>
        <tbody id="tablaProductos"></tbody>
      </table>
    </div>
  </section>

</main>

<script>
  const CSRF = <?= json_encode(tokenCsrf()) ?>;
</script>
<script src="../assets/js/conexion.js?v=<?= filemtime(__DIR__ . '/../assets/js/conexion.js') ?>"></script>
<script src="../assets/js/productos.js?v=<?= filemtime(__DIR__ . '/../assets/js/productos.js') ?>"></script>
</body>
</html>
