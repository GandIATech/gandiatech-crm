<?php
/**
 * Punto de entrada de la raíz del CRM (dominio o subcarpeta).
 *
 * Decide entre la aplicación y el asistente usando rutas reales de PHP, sin
 * depender de %{DOCUMENT_ROOT}: en algunos hostings esa variable no coincide
 * con la ruta del cliente FTP y la aplicación quedaba atrapada en /install/.
 *
 * Se copia tal cual como index.php en los paquetes distribuibles.
 */

require_once __DIR__ . '/src/php-guard.php';
crm_php_version_guard('8.2', false);

require __DIR__ . '/src/bootstrap.php';

if (app_installed()) {
    header('Location: ' . crm_url('/app/'), true, 302);
    exit;
}

if (is_dir(__DIR__ . '/install')) {
    header('Location: ' . crm_url('/install/'), true, 302);
    exit;
}

http_response_code(503);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>CRM sin instalar · GandiaTech CRM</title>
<style>
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#FBF9F5;color:#1F1B16;
font-family:system-ui,-apple-system,"Segoe UI",sans-serif;padding:24px;line-height:1.6}
main{max-width:560px;background:#fff;border:1px solid #E9E4DA;border-radius:20px;padding:28px}
h1{font-size:1.4rem;margin:0 0 .75rem}
p{margin:0 0 1rem}
code{background:#EFEAE1;border-radius:4px;padding:.1em .35em}
</style>
</head>
<body>
<main>
<h1>El CRM todavía no está instalado</h1>
<p>No se pudo cargar <code>config/config.php</code> y la carpeta
<code>install/</code> ya no está disponible, así que no hay nada que abrir.</p>
<p>Si la configuración existe, revisa que el fichero sea legible por PHP
(permisos <code>644</code>). Si no existe, vuelve a subir <code>install/</code>
a la carpeta del CRM y recarga esta página para completar la instalación.</p>
</main>
</body>
</html>
