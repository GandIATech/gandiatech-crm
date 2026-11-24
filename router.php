<?php
declare(strict_types=1);

/**
 * Servidor de desarrollo y front controller para `php -S`.
 *
 * Arranque:
 *   php -S 0.0.0.0:8002 router.php
 *
 * El router sirve todo el contenido estático de `public/` y delega `/api/*`
 * en el front controller de la API. No necesita Docker, Node ni Astro.
 */

require_once __DIR__ . '/src/Security.php';
Security::headers();

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = '/' . ltrim(rawurldecode($uriPath), '/');
if (str_contains($path, "\0")) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Petición no válida';
    return true;
}

// API JSON.
if ($path === '/api' || str_starts_with($path, '/api/')) {
    require __DIR__ . '/public/api/index.php';
    return true;
}

$installed = is_file(__DIR__ . '/config/config.php');

// Asistente de instalación. Debe ejecutarse como PHP, nunca servirse como
// archivo estático. Si ya se instaló, el asistente se elimina solo.
if ($path === '/install' || str_starts_with($path, '/install/')) {
    $installerFile = __DIR__ . '/public/install/index.php';
    if (is_file($installerFile)) {
        require $installerFile;
        return true;
    }
    if ($installed) {
        header('Location: /app/', true, 302);
        return true;
    }
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'El asistente de instalación no está disponible. Restaura la carpeta install/ o revisa config/config.php.';
    return true;
}

// Sin instalar, el frontend redirige al asistente. Se dejan pasar los assets
// que necesita la propia página de instalación y la API (que responderá 503).
if (!$installed) {
    $isAsset = str_starts_with($path, '/app/_astro/')
        || preg_match('#^/app/[^/]+(?:\.(?:css|js|png|svg|ico|webp|jpg|jpeg|woff2?))$#i', $path);
    if (!$isAsset && !str_starts_with($path, '/api/')) {
        header('Location: /install/', true, 302);
        return true;
    }
}

// La raíz redirige al frontend.
if ($path === '/') {
    header('Location: /app/', true, 302);
    return true;
}

if ($path === '/app') {
    header('Location: /app/', true, 302);
    return true;
}

$publicDir = realpath(__DIR__ . '/public');
if ($publicDir === false) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Falta la carpeta public/.';
    return true;
}

/**
 * Envía un archivo estático con su Content-Type y cache razonable.
 */
function crm_send_file(string $file, int $status = 200): void
{
    static $mimes = [
        'html' => 'text/html; charset=utf-8',
        'htm' => 'text/html; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
        'mjs' => 'text/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'txt' => 'text/plain; charset=utf-8',
        'map' => 'application/json; charset=utf-8',
        'webmanifest' => 'application/manifest+json',
    ];

    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $type = $mimes[$extension] ?? 'application/octet-stream';

    http_response_code($status);
    header('Content-Type: ' . $type);
    header('Content-Length: ' . (string) filesize($file));
    header('Cache-Control: ' . (in_array($extension, ['html', 'htm'], true) ? 'no-store, max-age=0' : 'public, max-age=86400'));

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        readfile($file);
    }
}

$candidate = realpath($publicDir . $path);

// Por seguridad, solo se sirve lo que esté dentro de public/.
if ($candidate !== false && str_starts_with($candidate, $publicDir . DIRECTORY_SEPARATOR)) {
    if (is_file($candidate)) {
        crm_send_file($candidate);
        return true;
    }

    if (is_dir($candidate)) {
        if (!str_ends_with($path, '/')) {
            header('Location: ' . $path . '/', true, 301);
            return true;
        }

        $index = $candidate . DIRECTORY_SEPARATOR . 'index.html';
        if (is_file($index)) {
            crm_send_file($index);
            return true;
        }
    }
}

// 404 del frontend.
if (str_starts_with($path, '/app/')) {
    $notFound = $publicDir . '/app/404.html';
    if (is_file($notFound)) {
        crm_send_file($notFound, 404);
        return true;
    }
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'No encontrado';
return true;
