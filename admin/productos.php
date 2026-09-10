<?php
/**
 * Gestión del catálogo: alta, edición, activación y borrado de productos.
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
      <div class="barra__grupo barra__grupo--operativa">
        <a class="barra__enlace" href="panel.php">Pedidos</a>
        <a class="barra__enlace" href="venta.php">Venta en mostrador</a>
      </div>
      <div class="barra__grupo barra__grupo--gestion">
        <a class="barra__enlace barra__enlace--activo" href="productos.php">Productos</a>
        <a class="barra__enlace" href="stock.php">Stock</a>
        <a class="barra__enlace" href="analitica.php">Analítica</a>
      </div>
      <a class="barra__enlace" href="salir.php">Salir</a>
    </nav>
  </div>
</header>

<main class="panel panel--estrecho">

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
            <th><span class="visualmente-oculto">Icono</span></th>
            <th>Producto</th>
            <th>Categoría</th>
            <th class="tabla__derecha">Precio</th>
            <th class="tabla__derecha">Stock</th>
            <th>Visible</th>
            <th><span class="visualmente-oculto">Acciones</span></th>
          </tr>
        </thead>
        <tbody id="tablaProductos"></tbody>
      </table>
    </div>
  </section>

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
      <div class="campo campo--corto">
        <label for="stockProducto">Stock</label>
        <input type="number" id="stockProducto" min="0" step="1" placeholder="Ilimitado">
      </div>
      <button class="boton boton--principal" type="submit">Añadir</button>
    </form>

    <div class="campo campo--iconos">
      <label>Icono <span class="campo__opcional">(opcional)</span></label>
      <div class="selector-iconos" id="selectorIconosAlta"></div>
    </div>

    <div class="campo">
      <label>Ingredientes <span class="campo__opcional">(opcional — si usa alguno, el stock de arriba se ignora)</span></label>
      <p class="campo__ayuda">
        Si este producto se prepara con ingredientes limitados (p. ej. 1 pan + 2 lonchas),
        añádelos aquí: su stock determinará cuántas unidades se pueden vender.
        Los ingredientes se gestionan en <a href="stock.php">Stock</a>.
      </p>
      <div class="receta" id="recetaAlta"></div>
      <button class="boton boton--pequeno boton--texto" type="button" id="botonAñadirIngredienteAlta">+ Añadir ingrediente</button>
    </div>

    <p class="campo__error" id="errorProducto" hidden></p>
  </section>

</main>

<!-- ============ Editar producto ============ -->
<dialog class="dialogo-producto" id="dialogoEditar">
  <form method="dialog" class="dialogo-producto__interior" id="formularioEditar">
    <header class="dialogo-producto__cabecera">
      <h2>Editar producto</h2>
      <button class="dialogo-producto__cerrar" type="button" id="botonCerrarEditar" aria-label="Cerrar">✕</button>
    </header>

    <div class="dialogo-producto__cuerpo">
      <input type="hidden" id="editarId">

      <div class="campo">
        <label for="editarNombre">Nombre</label>
        <input type="text" id="editarNombre" maxlength="100" required>
      </div>
      <div class="campo">
        <label for="editarCategoria">Categoría</label>
        <input type="text" id="editarCategoria" maxlength="50" list="categorias" required>
      </div>
      <div class="campo campo--corto">
        <label for="editarPrecio">Precio (€)</label>
        <input type="number" id="editarPrecio" min="0" max="999.99" step="0.05" required>
      </div>
      <div class="campo campo--corto">
        <label for="editarStock">Stock <span class="campo__opcional">(vacío = ilimitado)</span></label>
        <input type="number" id="editarStock" min="0" step="1" placeholder="Ilimitado">
      </div>

      <div class="campo campo--iconos">
        <label>Icono <span class="campo__opcional">(opcional)</span></label>
        <div class="selector-iconos" id="selectorIconosEditar"></div>
      </div>

      <div class="campo">
        <label>Ingredientes <span class="campo__opcional">(opcional — si usa alguno, el stock de arriba se ignora)</span></label>
        <div class="receta" id="recetaEditar"></div>
        <button class="boton boton--pequeno boton--texto" type="button" id="botonAñadirIngredienteEditar">+ Añadir ingrediente</button>
      </div>

      <p class="campo__error" id="errorEditar" hidden></p>
    </div>

    <footer class="dialogo-producto__pie">
      <button class="boton boton--texto" type="button" id="botonCancelarEditar">Cancelar</button>
      <button class="boton boton--principal" type="submit" id="botonGuardarEditar">Guardar cambios</button>
    </footer>
  </form>
</dialog>

<script>
  const CSRF = <?= json_encode(tokenCsrf()) ?>;
  // Versión del juego de iconos, para que el navegador no muestre recortes
  // antiguos cacheados cuando se regeneran.
  const VERSION_ICONOS = <?= json_encode((string) versionIconos()) ?>;
</script>
<script src="../assets/js/conexion.js?v=<?= filemtime(__DIR__ . '/../assets/js/conexion.js') ?>"></script>
<script src="../assets/js/productos.js?v=<?= filemtime(__DIR__ . '/../assets/js/productos.js') ?>"></script>
</body>
</html>
