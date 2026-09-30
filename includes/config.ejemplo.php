<?php
/**
 * PLANTILLA DE CONFIGURACIÓN
 * -----------------------------------------------------------------
 * Copia este archivo como  includes/config.php  y rellena los valores.
 * config.php NO se sube a GitHub (está en .gitignore) porque contiene
 * contraseñas. Súbelo por FTP directamente al servidor.
 */

return [

    // --- Base de datos (MySQL/MariaDB) -------------------------------
    'bd' => [
        'host'     => 'localhost',
        'nombre'   => 'PON_AQUI_EL_NOMBRE_DE_LA_BD',
        'usuario'  => 'PON_AQUI_EL_USUARIO_DE_LA_BD',
        'password' => 'PON_AQUI_LA_CONTRASENA_DE_LA_BD',
    ],

    // --- Acceso a la administración ---------------------------------
    // Código numérico de 4 cifras (no usuario/contraseña). No se guarda en
    // claro, sino su hash. Para generarlo, abre en el navegador:
    //   https://tu-web/admin/generar_hash.php?pin=1234
    // copia el resultado aquí y BORRA ese archivo del servidor.
    'admin' => [
        'pin_hash' => '$2y$10$SUSTITUIR_POR_EL_HASH_GENERADO',
    ],

    // --- Registro en Google Sheets -----------------------------------
    // El servidor PHP llama a este Apps Script (includes/arranque.php >
    // llamarAppsScript()) para registrar el pedido en la hoja y avisar por
    // correo al alumno. Nunca se envía al navegador.
    //
    // webhook: URL de la implementación del Apps Script (termina en /exec).
    // password: debe coincidir exactamente con la constante PASS del script.
    // Deja webhook vacío ('') para no registrar nada en el Sheet.
    'hoja' => [
        'webhook'  => '',
        'password' => '',
    ],

    // --- Ajustes generales ------------------------------------------
    'app' => [
        'nombre'          => 'La Cafetería del Tony',
        // Horas entre las que se aceptan pedidos (0-23). Fuera de ese
        // rango la web muestra un aviso. Pon [0, 24] para no limitar.
        'horario_pedidos' => [0, 24],
        // Máximo de unidades por producto en un mismo pedido.
        'max_por_producto' => 5,
        // Zona horaria para las fechas.
        'zona_horaria'    => 'Europe/Madrid',
    ],
];
