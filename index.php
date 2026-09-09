<?php
/**
 * Página pública: la que abren los alumnos al escanear el QR.
 * Muestra el catálogo y el formulario de pedido.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/arranque.php';

$productos = bd()->query(
    'SELECT id, nombre, categoria, precio
       FROM productos
      WHERE activo = 1
      ORDER BY orden, id'
)->fetchAll();

// Se agrupan por categoría para pintarlas en bloques.
$porCategoria = [];
foreach ($productos as $producto) {
    $porCategoria[$producto['categoria']][] = $producto;
}

$abierto = dentroDeHorario();
[$horaDesde, $horaHasta] = $CONFIG['app']['horario_pedidos'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($CONFIG['app']['nombre']) ?> · Haz tu pedido</title>
  <meta name="description" content="Pide tu bocadillo, croasant o sándwich en la cafetería del instituto y recógelo cuando esté listo.">
  <meta name="theme-color" content="#1f3d2b">
  <meta name="robots" content="noindex">
  <link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body class="pagina-pedido">

<header class="cabecera">
  <div class="contenedor cabecera__interior">
    <span class="marca"><?= e($CONFIG['app']['nombre']) ?></span>
    <span class="estado-apertura <?= $abierto ? 'estado-apertura--abierto' : 'estado-apertura--cerrado' ?>">
      <?= $abierto ? 'Abierto' : 'Cerrado' ?>
    </span>
  </div>
</header>

<main class="contenedor contenedor--estrecho">

  <!-- ============ Confirmación (oculta hasta que se envía) ============ -->
  <section class="confirmacion" id="confirmacion" hidden aria-live="polite">
    <div class="confirmacion__icono" aria-hidden="true">✓</div>
    <h1 class="confirmacion__titulo">¡Pedido recibido!</h1>
    <p>Enseña este código en la barra cuando te avisemos:</p>
    <p class="confirmacion__codigo" id="codigoPedido"></p>
    <p class="confirmacion__texto">
      Te enviaremos un correo a <strong id="emailConfirmacion"></strong>
      en cuanto esté preparado.
    </p>
    <button class="boton boton--secundario" type="button" id="botonOtroPedido">Hacer otro pedido</button>
  </section>

  <!-- ============ Formulario de pedido ============ -->
  <div id="bloquePedido">

    <div class="portada-pedido">
      <h1>Haz tu pedido</h1>
      <p>Elige lo que quieras, déjanos tus datos y te avisamos por correo cuando esté listo. Se paga al recoger.</p>
    </div>

    <?php if (!$abierto): ?>
      <p class="aviso aviso--atencion">
        Ahora mismo no se admiten pedidos. El horario es de las
        <?= (int) $horaDesde ?>:00 a las <?= (int) $horaHasta ?>:00.
      </p>
    <?php endif; ?>

    <?php if (!$productos): ?>
      <p class="aviso aviso--atencion">
        No hay productos disponibles en este momento.
      </p>
    <?php else: ?>

    <form id="formularioPedido" novalidate <?= $abierto ? '' : 'inert' ?>>

      <!-- ---------- Productos ---------- -->
      <section class="bloque">
        <h2 class="bloque__titulo">1. ¿Qué quieres?</h2>

        <?php foreach ($porCategoria as $categoria => $lista): ?>
          <h3 class="categoria"><?= e($categoria) ?></h3>
          <ul class="productos">
            <?php foreach ($lista as $p): ?>
              <li class="producto" data-precio="<?= e((string) $p['precio']) ?>">
                <div class="producto__info">
                  <span class="producto__nombre"><?= e($p['nombre']) ?></span>
                  <span class="producto__precio"><?= number_format((float) $p['precio'], 2, ',', '.') ?> €</span>
                </div>
                <div class="contador" data-producto="<?= (int) $p['id'] ?>">
                  <button type="button" class="contador__boton" data-accion="restar"
                          aria-label="Quitar una unidad de <?= e($p['nombre']) ?>">−</button>
                  <input class="contador__valor" type="number" inputmode="numeric"
                         name="producto_<?= (int) $p['id'] ?>" value="0"
                         min="0" max="<?= (int) $CONFIG['app']['max_por_producto'] ?>"
                         aria-label="Unidades de <?= e($p['nombre']) ?>">
                  <button type="button" class="contador__boton" data-accion="sumar"
                          aria-label="Añadir una unidad de <?= e($p['nombre']) ?>">+</button>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endforeach; ?>

        <p class="campo__error" id="error-productos" hidden></p>
      </section>

      <!-- ---------- Datos del alumno ---------- -->
      <section class="bloque">
        <h2 class="bloque__titulo">2. Tus datos</h2>

        <div class="campo">
          <label for="nombre">Nombre y apellidos</label>
          <input type="text" id="nombre" name="nombre" autocomplete="name" maxlength="120" required>
          <p class="campo__error" id="error-nombre" hidden></p>
        </div>

        <div class="campo">
          <label for="dni">DNI</label>
          <input type="text" id="dni" name="dni" maxlength="12"
                 placeholder="12345678Z" autocapitalize="characters" required>
          <p class="campo__error" id="error-dni" hidden></p>
        </div>

        <div class="campo">
          <label for="email">Correo electrónico</label>
          <input type="email" id="email" name="email" autocomplete="email" maxlength="150"
                 placeholder="nombre@ejemplo.com" required>
          <p class="campo__ayuda">Aquí te avisamos cuando el pedido esté preparado.</p>
          <p class="campo__error" id="error-email" hidden></p>
        </div>

        <div class="campo">
          <label for="notas">Observaciones <span class="campo__opcional">(opcional)</span></label>
          <textarea id="notas" name="notas" rows="2" maxlength="255"
                    placeholder="Sin tomate, para el recreo de las 11..."></textarea>
        </div>
      </section>

      <!-- ---------- Resumen y envío ---------- -->
      <div class="resumen" id="resumen">
        <div class="resumen__linea">
          <span id="resumenUnidades">0 productos</span>
          <strong id="resumenTotal">0,00 €</strong>
        </div>
        <button class="boton boton--principal boton--ancho" type="submit" id="botonEnviar">
          Enviar pedido
        </button>
        <p class="campo__error" id="error-general" hidden></p>
      </div>

    </form>
    <?php endif; ?>
  </div>
</main>

<footer class="pie">
  <div class="contenedor">
    <p><?= e($CONFIG['app']['nombre']) ?></p>
  </div>
</footer>

<script src="assets/js/pedido.js"></script>
</body>
</html>
