<?php
/**
 * Plantilla de configuración. Copia este archivo como config/config.php y
 * genera una app_key propia con: php -r "echo bin2hex(random_bytes(32));"
 *
 * El instalador (/install/) crea este archivo automáticamente con una clave
 * aleatoria; esta plantilla solo es necesaria si configuras el CRM a mano.
 */
return [
    'app_key' => 'CAMBIAR_POR_UNA_CLAVE_ALEATORIA_DE_64_CARACTERES_HEXADECIMALES',
    'app_url' => getenv('CRM_APP_URL') ?: '',
    'debug' => false,
    'installed' => true,
    'db' => [
        'driver' => 'sqlite',
        'path' => 'data/crm.sqlite',
    ],
    // Buzón que recibe los comentarios enviados desde la ayuda del CRM.
    // El correo sale por el SMTP configurado en Configuración → Correo.
    'feedback_email' => 'contacto@gandia.tech',
];
