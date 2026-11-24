<?php
/**
 * Punto de entrada del asistente de instalación.
 *
 * Este archivo se ejecuta antes que el resto de la aplicación, así que debe
 * poder interpretarse con cualquier PHP: si el servidor es demasiado antiguo,
 * el aviso se muestra desde aquí en lugar de un error 500.
 *
 * El asistente en sí está en instalar.php (PHP 8.2+).
 */

$crmRoot = dirname(__DIR__);
if (!is_file($crmRoot . '/src/php-guard.php')) {
    $crmRoot = dirname(__DIR__, 2);
}

require_once $crmRoot . '/src/php-guard.php';
crm_php_version_guard(crm_php_minimum(), false);

define('CRM_INSTALL_ENTRY', true);
require __DIR__ . '/instalar.php';
