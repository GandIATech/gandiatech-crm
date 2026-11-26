<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

/** Versión publicada de la aplicación. Debe coincidir con el tag del release. */
define('APP_VERSION', '1.3.0');

/**
 * Clave pública Ed25519 con la que se verifica la firma de latest.json.
 * La clave privada correspondiente solo debe existir en GitHub Secrets
 * (UPDATE_SIGNING_KEY), nunca en el repositorio ni en el servidor.
 */
define('UPDATE_PUBLIC_KEY', 'zvxwJAuTH1JUP882GE7qQ49e1NjCvoUKAjLJqXKoTMk=');

/** Manifiesto estable del último release publicado (incluye su firma .sig). */
define('UPDATE_MANIFEST_URL', 'https://github.com/GandIATech/gandiatech-crm/releases/latest/download/latest.json');

require BASE_PATH . '/vendor/autoload.php';

date_default_timezone_set('Europe/Madrid');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (!is_dir(BASE_PATH . '/data')) {
    mkdir(BASE_PATH . '/data', 0775, true);
}
ini_set('error_log', BASE_PATH . '/data/php-error.log');

$configFile = BASE_PATH . '/config/config.php';
if (!is_dir(dirname($configFile))) {
    mkdir(dirname($configFile), 0775, true);
}

$GLOBALS['crm_config'] = is_file($configFile) ? (require $configFile) : [];
if (!is_array($GLOBALS['crm_config'])) {
    $GLOBALS['crm_config'] = [];
}

function config(string $key, mixed $default = null): mixed
{
    return $GLOBALS['crm_config'][$key] ?? $default;
}

/**
 * Una instalación es válida cuando existe config/config.php y no está marcada
 * expresamente como pendiente. Las configuraciones antiguas (sin la clave
 * `installed`) se consideran instaladas.
 */
function app_installed(): bool
{
    return is_file(BASE_PATH . '/config/config.php')
        && config('installed', true) !== false;
}

function base_url(): string
{
    $configured = trim((string) config('app_url', ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    if (PHP_SAPI === 'cli' && empty($_SERVER['HTTP_HOST'])) {
        return 'http://localhost:8002';
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $rawHost = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost:8002');
    // Solo se aceptan nombres de dominio, IPv4 e IPv6 con puerto opcional.
    if (preg_match('/^[a-zA-Z0-9.\-:\[\]]{1,253}$/', $rawHost) !== 1) {
        $rawHost = 'localhost:8002';
    }
    return ($https ? 'https' : 'http') . '://' . $rawHost;
}

/**
 * Prefijo de URL cuando la aplicación se publica dentro de una subcarpeta
 * (por ejemplo /crm). Devuelve '' si está en la raíz del dominio.
 */
function crm_base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    // Con `php -S … router.php` SCRIPT_NAME es la ruta pedida, no la del script,
    // así que no sirve para deducir la subcarpeta. En ese servidor el router
    // siempre se lanza desde la raíz del paquete.
    if (PHP_SAPI === 'cli-server') {
        return $base = '';
    }
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    $dir = (string) preg_replace('#/(?:api|install)$#', '', $dir);
    $base = ($dir === '' || $dir === '.' || $dir === '/') ? '' : $dir;
    return $base;
}

/** Construye una ruta absoluta de la aplicación respetando la subcarpeta. */
function crm_url(string $path): string
{
    return crm_base_path() . '/' . ltrim($path, '/');
}

require __DIR__ . '/helpers.php';
require __DIR__ . '/Database.php';
require __DIR__ . '/Http.php';
require_once __DIR__ . '/Security.php';
require __DIR__ . '/Settings.php';
require __DIR__ . '/Auth.php';
require __DIR__ . '/Mailer.php';
require __DIR__ . '/Notifier.php';

if (app_installed()) {
    Database::init();
}
