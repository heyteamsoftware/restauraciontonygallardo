<?php
/**
 * PLANTILLA DE CONFIGURACIÓN
 * -----------------------------------------------------------------
 * Copia este archivo como  includes/config.php  y rellena los valores.
 * config.php NO se sube a GitHub (está en .gitignore) porque contiene
 * contraseñas. Súbelo por FTP directamente al servidor.
 */

return [

    // --- Base de datos (panel de InfinityFree > MySQL Databases) ---
    'bd' => [
        'host'     => 'sqlXXX.infinityfree.com',
        'nombre'   => 'if0_41719563_cafeteria',
        'usuario'  => 'if0_41719563',
        'password' => 'PON_AQUI_LA_CONTRASENA_DE_LA_BD',
    ],

    // --- Acceso a la administración ---------------------------------
    // La contraseña NO se guarda en claro, sino su hash. Para generarlo,
    // abre en el navegador:  https://tu-web/admin/generar_hash.php?clave=loquesea
    // copia el resultado aquí y BORRA ese archivo del servidor.
    'admin' => [
        'usuario'   => 'cafeteria',
        'hash_pass' => '$2y$10$SUSTITUIR_POR_EL_HASH_GENERADO',
    ],

    // --- Aviso por email al alumno ----------------------------------
    // InfinityFree bloquea la función mail() de PHP, así que se envía
    // a través de la API HTTPS de un proveedor externo (plan gratuito).
    //
    //   proveedor: 'ninguno' | 'brevo' | 'resend'
    //   'ninguno' = la app funciona igual, pero no envía correos.
    'email' => [
        'proveedor'    => 'ninguno',
        'api_key'      => '',
        'remitente'    => 'cafeteria@tudominio.com',
        'nombre_envio' => 'Cafetería del instituto',
    ],

    // --- Ajustes generales ------------------------------------------
    'app' => [
        'nombre'          => 'Cafetería del instituto',
        // Horas entre las que se aceptan pedidos (0-23). Fuera de ese
        // rango la web muestra un aviso. Pon [0, 24] para no limitar.
        'horario_pedidos' => [7, 14],
        // Máximo de unidades por producto en un mismo pedido.
        'max_por_producto' => 5,
        // Zona horaria para las fechas.
        'zona_horaria'    => 'Europe/Madrid',
    ],
];
