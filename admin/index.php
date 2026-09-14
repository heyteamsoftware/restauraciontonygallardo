<?php
/**
 * Acceso al panel de administración: código numérico de 4 cifras.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if (hayAdmin()) {
    header('Location: panel.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!comprobarCsrf($_POST['csrf'] ?? null)) {
        $error = 'La sesión ha caducado. Inténtalo otra vez.';
    } elseif (pinCorrecto((string) ($_POST['pin'] ?? ''))) {
        iniciarSesionAdmin();
        header('Location: panel.php');
        exit;
    } else {
        $error = 'Código incorrecto.';
        sleep(1); // frena los intentos por fuerza bruta
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Acceso · <?= e($CONFIG['app']['nombre']) ?></title>
  <meta name="robots" content="noindex">
  <link rel="icon" href="../assets/img/icono/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/icono/favicon-32.png">
  <link rel="apple-touch-icon" href="../assets/img/icono/apple-touch-icon.png">
  <link rel="stylesheet" href="../assets/css/estilos.css?v=<?= filemtime(__DIR__ . '/../assets/css/estilos.css') ?>">
</head>
<body class="pagina-login">

<main class="login">
  <form class="login__caja" method="post" action="index.php">
    <h1 class="login__titulo">Cafetería</h1>
    <p class="login__subtitulo">Panel de administración</p>

    <?php if ($error): ?>
      <p class="aviso aviso--error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <div class="campo campo--pin">
      <label for="pin">Código de acceso</label>
      <input type="tel" id="pin" name="pin" inputmode="numeric" pattern="\d{4}" maxlength="4"
             autocomplete="off" required autofocus placeholder="····">
    </div>

    <input type="hidden" name="csrf" value="<?= e(tokenCsrf()) ?>">
    <button class="boton boton--principal boton--ancho" type="submit">Entrar</button>
  </form>
</main>

<script>
  // Solo dígitos, y enviar solo cuando se han tecleado las 4 cifras.
  const campoPin = document.getElementById('pin');
  campoPin.addEventListener('input', () => {
    campoPin.value = campoPin.value.replace(/\D/g, '').slice(0, 4);
  });
</script>
</body>
</html>
