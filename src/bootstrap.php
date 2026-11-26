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

// Mientras se copian los ficheros de una actualización, el resto de peticiones
// recibe una página de mantenimiento breve. Si el proceso murió a medias, el
// aviso caduca solo para no dejar el CRM bloqueado.
if (PHP_SAPI !== 'cli' && app_installed() && is_file(BASE_PATH . '/data/maintenance.json')) {
    $rawMaintenance = @file_get_contents(BASE_PATH . '/data/maintenance.json');
    $maintenance = $rawMaintenance !== false ? json_decode($rawMaintenance, true) : null;
    $startedAt = is_array($maintenance) ? (int) ($maintenance['started_at'] ?? 0) : 0;
    if ($startedAt > 0 && time() - $startedAt < 900) {
        http_response_code(503);
        header('Retry-After: 60');
        header('Cache-Control: no-store');
        if (str_contains((string) ($_SERVER['REQUEST_URI'] ?? ''), '/api/')) {
            json_error('El CRM se está actualizando. Vuelve en un minuto.', 503);
        }
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>Actualizando el CRM</title></head>'
            . '<body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#FBF9F5;color:#1F1B16;font-family:system-ui,-apple-system,Segoe UI,sans-serif;padding:24px">'
            . '<main style="max-width:520px;background:#fff;border:1px solid #E9E4DA;border-radius:20px;padding:28px;text-align:center">'
            . '<h1 style="font-size:1.35rem;margin:0 0 .6rem">Actualizando el CRM</h1>'
            . '<p style="margin:0;color:#5E6472;line-height:1.6">Estamos instalando una versión nueva. Esta página se recargará en un minuto.</p>'
            . '<script>setTimeout(function(){location.reload();},60000);</script>'
            . '</main></body></html>';
        exit;
    }
    @unlink(BASE_PATH . '/data/maintenance.json');
}

if (app_installed()) {
    Database::init();
}
