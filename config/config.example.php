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
    // Incidencias de usuarios: se crean como issues en GitHub mediante la API.
    // El token debe ser fine-grained (o clásico) con permiso «Issues: write»
    // sobre el repositorio. Sin token, el botón de reporte devuelve un aviso.
    'github' => [
        'repo' => 'GandIATech/gandiatech-crm',
        'token' => '',
        'api' => 'https://api.github.com',
    ],
];
