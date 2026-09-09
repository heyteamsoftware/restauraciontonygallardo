<?php
/**
 * Acceso al panel de administración.
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
    } elseif (credencialesCorrectas((string) ($_POST['usuario'] ?? ''), (string) ($_POST['password'] ?? ''))) {
        iniciarSesionAdmin((string) $_POST['usuario']);
        header('Location: panel.php');
        exit;
    } else {
        // Mensaje genérico a propósito: no se revela qué campo ha fallado.
        $error = 'Usuario o contraseña incorrectos.';
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

    <div class="campo">
      <label for="usuario">Usuario</label>
      <input type="text" id="usuario" name="usuario" autocomplete="username" required autofocus>
    </div>

    <div class="campo">
      <label for="password">Contraseña</label>
      <input type="password" id="password" name="password" autocomplete="current-password" required>
    </div>

    <input type="hidden" name="csrf" value="<?= e(tokenCsrf()) ?>">
    <button class="boton boton--principal boton--ancho" type="submit">Entrar</button>
  </form>
</main>

</body>
</html>
