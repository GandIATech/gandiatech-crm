<?php
declare(strict_types=1);

/**
 * Router para la versión distribuible (diseño de carpetas raíz).
 *
 * Arranque local:
 *   php -S 0.0.0.0:8002 router.php
 */

require_once __DIR__ . '/src/Security.php';
Security::headers();

$root = __DIR__;
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = '/' . ltrim(rawurldecode((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/')), '/');
if (str_contains($path, "\0")) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Petición no válida';
    return true;
}

if ($path === '/api' || str_starts_with($path, '/api/')) {
    require $root . '/api/index.php';
    return true;
}

$installed = is_file($root . '/config/config.php');

if ($path === '/install' || str_starts_with($path, '/install/')) {
    $installerFile = $root . '/install/index.php';
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

if (!$installed) {
    $isAsset = preg_match('#^/app/(?:_astro/|[^/]+\.(?:css|js|png|svg|ico|webp|jpg|jpeg|woff2?))$#i', $path);
    if (!$isAsset) {
        header('Location: /install/', true, 302);
        return true;
    }
}

if ($path === '/' || $path === '/app') {
    header('Location: /app/', true, 302);
    return true;
}

$appDir = realpath($root . '/app');
$candidate = realpath($root . $path);
if ($appDir !== false && $candidate !== false
    && ($candidate === $appDir || str_starts_with($candidate, $appDir . DIRECTORY_SEPARATOR))) {
    if (is_file($candidate)) {
        $extension = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
        $mimes = [
            'html' => 'text/html; charset=utf-8',
            'css' => 'text/css; charset=utf-8',
            'js' => 'text/javascript; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
        ];
        http_response_code(200);
        header('Content-Type: ' . ($mimes[$extension] ?? 'application/octet-stream'));
        header('Content-Length: ' . (string) filesize($candidate));
        header('Cache-Control: ' . (in_array($extension, ['html', 'htm'], true) ? 'no-store, max-age=0' : 'public, max-age=86400'));
        if ($method !== 'HEAD') {
            readfile($candidate);
        }
        return true;
    }

    if (is_dir($candidate)) {
        if (!str_ends_with($path, '/')) {
            header('Location: ' . $path . '/', true, 301);
            return true;
        }
        $index = $candidate . DIRECTORY_SEPARATOR . 'index.html';
        if (is_file($index)) {
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-cache');
            readfile($index);
            return true;
        }
    }
}

if (str_starts_with($path, '/app/') && is_file($root . '/app/404.html')) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    readfile($root . '/app/404.html');
    return true;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'No encontrado';
return true;
