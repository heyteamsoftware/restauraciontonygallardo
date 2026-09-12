<?php
/**
 * Página pública: la que abren los alumnos al escanear el QR.
 * Flujo en 2 pantallas: 1) elegir productos  2) datos y enviar.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/arranque.php';
require_once __DIR__ . '/includes/iconos_productos.php';

$productos = catalogoConStock();

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
  <title><?= e($CONFIG['app']['nombre']) ?> · Pedidos</title>
  <meta name="description" content="Pide tu bocadillo, croasant o sándwich en la cafetería del instituto y recógelo cuando esté listo.">
  <meta name="theme-color" content="#1f3d2b">
  <meta name="robots" content="noindex">
  <link rel="stylesheet" href="assets/css/estilos.css?v=<?= filemtime(__DIR__ . '/assets/css/estilos.css') ?>">
</head>
<body class="pagina-pedido">

<header class="cabecera">
  <div class="contenedor cabecera__interior">
    <span class="marca"><?= e($CONFIG['app']['nombre']) ?></span>
    <div class="cabecera__indicadores">
      <span class="indicador-conexion" id="indicadorConexion" aria-live="polite"></span>
      <span class="estado-apertura <?= $abierto ? 'estado-apertura--abierto' : 'estado-apertura--cerrado' ?>">
        <?= $abierto ? 'Abierto' : 'Cerrado' ?>
      </span>
    </div>
  </div>
</header>

<main class="contenedor contenedor--estrecho">

  <!-- ============ Confirmación (oculta hasta que se envía) ============ -->
  <section class="confirmacion" id="confirmacion" hidden aria-live="polite">
    <div class="confirmacion__icono" aria-hidden="true">✓</div>
    <h1 class="confirmacion__titulo">¡Pedido recibido!</h1>
    <p class="confirmacion__nombre" id="confirmacionNombre"></p>
    <p>Enseña este código en la barra cuando te avisemos:</p>
    <p class="confirmacion__codigo" id="codigoPedido"></p>
    <p class="confirmacion__texto" id="confirmacionTexto"></p>
    <button class="boton boton--secundario" type="button" id="botonOtroPedido">Hacer otro pedido</button>
  </section>

  <!-- ============ Formulario de pedido (2 pantallas) ============ -->
  <div id="bloquePedido">

    <?php if (!$abierto): ?>
      <p class="aviso aviso--atencion">
        Ahora mismo no se admiten pedidos. Horario: <?= (int) $horaDesde ?>:00–<?= (int) $horaHasta ?>:00.
      </p>
    <?php endif; ?>

    <?php if (!$productos): ?>
      <p class="aviso aviso--atencion">No hay productos disponibles en este momento.</p>
    <?php else: ?>

    <form id="formularioPedido" novalidate <?= $abierto ? '' : 'inert' ?>>

      <!-- ---------- PASO 1: Productos ---------- -->
      <section class="paso" id="paso1" data-paso="1">
        <?php foreach ($porCategoria as $categoria => $lista): ?>
          <h2 class="categoria"><?= e($categoria) ?></h2>
          <ul class="productos">
            <?php foreach ($lista as $p):
              $stock = $p['stock'] !== null ? (int) $p['stock'] : null;
              $max   = $stock !== null ? min((int) $CONFIG['app']['max_por_producto'], $stock) : (int) $CONFIG['app']['max_por_producto'];
              $agotado = $stock === 0;
            ?>
              <li class="producto<?= $agotado ? ' producto--agotado' : '' ?>" data-precio="<?= e((string) $p['precio']) ?>"
                  data-producto-id="<?= (int) $p['id'] ?>" data-stock="<?= $stock === null ? '' : $stock ?>"
                  data-ingredientes="<?= e(json_encode($p['ingredientes'])) ?>">
                <?php if ($p['icono']): ?>
                  <img class="producto__icono" src="assets/img/productos/<?= e($p['icono']) ?>?v=<?= versionIconos() ?>" alt="" loading="lazy">
                <?php endif; ?>
                <div class="producto__info">
                  <span class="producto__nombre"><?= e($p['nombre']) ?></span>
                  <span class="producto__precio"><?= number_format((float) $p['precio'], 2, ',', '.') ?> €</span>
                  <span class="producto__stock" <?= $stock === null ? 'hidden' : '' ?>>
                    <?= $agotado ? 'Agotado' : 'Quedan ' . $stock ?>
                  </span>
                </div>
                <div class="contador" data-producto="<?= (int) $p['id'] ?>">
                  <button type="button" class="contador__boton" data-accion="restar"
                          aria-label="Quitar una unidad de <?= e($p['nombre']) ?>" <?= $agotado ? 'disabled' : '' ?>>−</button>
                  <input class="contador__valor" type="number" inputmode="numeric"
                         name="producto_<?= (int) $p['id'] ?>" value="0"
                         min="0" max="<?= $max ?>" <?= $agotado ? 'disabled' : '' ?>
                         aria-label="Unidades de <?= e($p['nombre']) ?>">
                  <button type="button" class="contador__boton" data-accion="sumar"
                          aria-label="Añadir una unidad de <?= e($p['nombre']) ?>" <?= $agotado ? 'disabled' : '' ?>>+</button>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endforeach; ?>

        <p class="campo__error" id="error-productos" hidden></p>

        <div class="barra-inferior">
          <div class="barra-inferior__linea">
            <span id="resumenUnidades">0 productos</span>
            <strong id="resumenTotal">0,00 €</strong>
          </div>
          <button class="boton boton--principal boton--ancho" type="button" id="botonSiguiente">
            Continuar
          </button>
        </div>
      </section>

      <!-- ---------- PASO 2: Datos del alumno ---------- -->
      <section class="paso" id="paso2" data-paso="2" hidden>
        <button class="enlace-volver" type="button" id="botonVolver">← Volver a los productos</button>

        <div class="resumen-pedido" id="resumenPedido"></div>

        <div class="campo">
          <label for="nombre">Nombre y apellidos <span class="campo__obligatorio">(obligatorio)</span></label>
          <input type="text" id="nombre" name="nombre" autocomplete="name" maxlength="120" required>
          <p class="campo__error" id="error-nombre" hidden></p>
        </div>

        <div class="campo">
          <label for="email">Correo electrónico <span class="campo__opcional">(opcional)</span></label>
          <input type="email" id="email" name="email" autocomplete="email" maxlength="150"
                 placeholder="nombre@ejemplo.com">
          <p class="campo__ayuda">Si lo dejas, te avisamos aquí en cuanto tu pedido esté listo.</p>
          <p class="campo__error" id="error-email" hidden></p>
        </div>

        <div class="campo">
          <label for="notas">Observaciones <span class="campo__opcional">(opcional)</span></label>
          <textarea id="notas" name="notas" rows="2" maxlength="255"
                    placeholder="Sin tomate, para el recreo de las 11..."></textarea>
        </div>

        <p class="campo__error" id="error-general" hidden></p>

        <button class="boton boton--principal boton--ancho" type="submit" id="botonEnviar">
          Enviar pedido
        </button>

        <button class="enlace-privacidad" type="button" id="botonPrivacidad">
          Política de privacidad
        </button>
      </section>

    </form>
    <?php endif; ?>
  </div>
</main>

<!-- ============ Política de privacidad ============ -->
<dialog class="dialogo-privacidad" id="dialogoPrivacidad">
  <form method="dialog" class="dialogo-privacidad__interior">
    <header class="dialogo-privacidad__cabecera">
      <h2>Protección de datos</h2>
      <button class="dialogo-privacidad__cerrar" type="submit" aria-label="Cerrar">✕</button>
    </header>

    <div class="dialogo-privacidad__cuerpo">

      <h3>Información básica</h3>
      <ul>
        <li><strong>Responsable:</strong> CIFP Tony Gallardo.</li>
        <li><strong>Contacto:</strong> Ctra. de las Coloradas, 35009 Las Palmas de Gran Canaria · Tel. +34 928 79 62 92.</li>
        <li><strong>Finalidad:</strong> Gestionar la comanda del pedido e identificarlo (mediante nombre o apodo) y, opcionalmente, enviar la confirmación/estado por correo electrónico.</li>
        <li><strong>Legitimación:</strong> Ejecución de la solicitud de pedido y consentimiento del usuario al facilitar el correo opcional.</li>
        <li><strong>Conservación:</strong> Los datos se guardan de forma temporal durante el curso escolar, con fines de gestión y estadística de la cafetería, y se eliminan o anonimizan al finalizar el curso.</li>
        <li><strong>Derechos:</strong> Puedes solicitar la supresión o acceso a tus datos escribiendo a <a href="mailto:secretaria@iestonygallardo.com">secretaria@iestonygallardo.com</a>.</li>
      </ul>

      <h3>Política de privacidad completa</h3>

      <h4>1. Responsable del tratamiento</h4>
      <ul>
        <li><strong>Titular:</strong> CIFP Tony Gallardo</li>
        <li><strong>Domicilio:</strong> Ctra. de las Coloradas, 35009 Las Palmas de Gran Canaria</li>
        <li><strong>Teléfono:</strong> +34 928 79 62 92</li>
        <li><strong>Correo electrónico de contacto:</strong> <a href="mailto:secretaria@iestonygallardo.com">secretaria@iestonygallardo.com</a></li>
      </ul>

      <h4>2. Datos personales recabados</h4>
      <p>Para el uso del servicio de pedidos de la aplicación, únicamente se solicitan los siguientes datos:</p>
      <ul>
        <li><strong>Nombre o pseudónimo (obligatorio):</strong> no requiere verificación de identidad real. Se utiliza exclusivamente para identificar el pedido al momento de la entrega o recogida. <strong>No se solicita ni se guarda ningún documento de identidad (DNI/NIE) en ningún momento del proceso.</strong></li>
        <li><strong>Correo electrónico (opcional):</strong> se solicita únicamente si el usuario desea recibir notificaciones o confirmaciones sobre el estado de su pedido.</li>
      </ul>

      <h4>3. Finalidad y base jurídica del tratamiento</h4>
      <ul>
        <li><strong>Gestión operativa del pedido (nombre/alias):</strong> la base legal es la prestación del servicio solicitado por el usuario (art. 6.1.b del RGPD).</li>
        <li><strong>Envío de notificaciones (correo opcional):</strong> la base legal es el consentimiento explícito brindado por el usuario al introducir voluntariamente su dirección de correo electrónico (art. 6.1.a del RGPD).</li>
      </ul>

      <h4>4. Plazo de conservación de los datos</h4>
      <p>En cumplimiento del principio de minimización y limitación del plazo de conservación:</p>
      <ul>
        <li>Los datos se procesan en el momento de la comanda para gestionar el pedido y su entrega.</li>
        <li>Además, se conservan de forma temporal durante el curso escolar en curso con fines de gestión interna y estadísticos de la cafetería (por ejemplo, para saber qué productos se piden más).</li>
        <li>Al finalizar el curso escolar, los datos personales serán eliminados o anonimizados completamente de la base de datos de la aplicación.</li>
      </ul>

      <h4>5. Destinatarios y cesiones</h4>
      <p>
        No se cederán datos a terceros salvo obligación legal. En caso de alojar la aplicación en
        servidores externos (servicios en la nube o hosting), estos actuarán bajo la condición de
        Encargados del Tratamiento garantizando los estándares de seguridad exigidos por el RGPD.
      </p>

      <h4>6. Derechos de los usuarios</h4>
      <p>
        Cualquier usuario puede ejercer sus derechos de acceso, rectificación, supresión, limitación
        del tratamiento y oposición enviando una solicitud a
        <a href="mailto:secretaria@iestonygallardo.com">secretaria@iestonygallardo.com</a>
        o por escrito a la dirección física del centro.
      </p>
      <p>
        Asimismo, los usuarios tienen derecho a presentar una reclamación ante la Agencia Española
        de Protección de Datos (<a href="https://www.aepd.es" target="_blank" rel="noopener">www.aepd.es</a>)
        si consideran que sus datos no han sido tratados conforme a la normativa vigente.
      </p>

      <h4>7. Seguridad de la información</h4>
      <p>
        El centro aplica las medidas técnicas y organizativas necesarias (como el cifrado en
        tránsito mediante HTTPS/TLS) para garantizar la confidencialidad de la información enviada
        a través de la aplicación.
      </p>

    </div>
  </form>
</dialog>

<script src="assets/js/conexion.js?v=<?= filemtime(__DIR__ . '/assets/js/conexion.js') ?>"></script>
<script src="assets/js/pedido.js?v=<?= filemtime(__DIR__ . '/assets/js/pedido.js') ?>"></script>
</body>
</html>
