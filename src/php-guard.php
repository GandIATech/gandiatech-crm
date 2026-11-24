<?php
/**
 * Guardia de versión de PHP.
 *
 * Se carga antes que el resto de la aplicación para que, si el servidor tiene
 * una versión antigua, el usuario vea una página explicativa en lugar de un
 * error 500 sin información.
 *
 * IMPORTANTE: este archivo debe seguir siendo compatible con PHP antiguo
 * (5.3+): sin tipos escalares, sin arrays cortos, sin operadores nuevos y sin
 * funciones de PHP 7/8. Si no, el propio aviso provocaría el error que intenta
 * explicar.
 */

if (!function_exists('crm_php_minimum')) {
    function crm_php_minimum()
    {
        return '8.2';
    }

    /**
     * @param string $minimum Versión mínima requerida.
     * @param bool   $asJson  Devuelve JSON (API) en lugar de HTML.
     */
    function crm_php_version_guard($minimum, $asJson)
    {
        if (version_compare(PHP_VERSION, $minimum, '>=')) {
            return;
        }

        $current = PHP_VERSION;

        if (!headers_sent()) {
            http_response_code(503);
            header('Cache-Control: no-store');
            header($asJson ? 'Content-Type: application/json; charset=utf-8' : 'Content-Type: text/html; charset=utf-8');
        }

        if ($asJson) {
            echo json_encode(array(
                'error' => true,
                'message' => 'Este servidor usa PHP ' . $current . ' y GandiaTech CRM necesita PHP ' . $minimum
                    . ' o superior. Cambia la versión de PHP del dominio y vuelve a intentarlo.',
                'php' => $current,
                'required' => $minimum,
            ));
            exit;
        }

        $html = '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>PHP %1$s no es compatible · GandiaTech CRM</title>
<style>
:root{color-scheme:light}
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#FBF9F5;color:#1E2230;
font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;padding:24px;line-height:1.6}
main{width:100%%;max-width:620px;background:#fff;border:1px solid #E9E4DA;border-radius:24px;
padding:32px;box-shadow:0 18px 44px -30px rgba(30,34,48,.35)}
p.kicker{margin:0 0 6px;font-size:11px;font-weight:800;letter-spacing:.18em;text-transform:uppercase;color:#C7381A}
h1{margin:0 0 12px;font-size:24px;line-height:1.2}
p{margin:0 0 14px;font-size:14px;color:#4A5060}
strong{color:#1E2230}
ul{margin:0 0 18px;padding-left:20px;font-size:14px;color:#4A5060}
li{margin-bottom:8px}
code{background:#F1EFEA;border-radius:5px;padding:1px 5px;font-size:12.5px}
.box{background:#FFF7ED;border:1px solid #FDE68A;border-radius:14px;padding:12px 14px;font-size:13px;color:#92400E;margin-bottom:18px}
a.btn{display:inline-block;background:#FF5A36;color:#fff;text-decoration:none;font-weight:700;
font-size:13px;padding:11px 20px;border-radius:999px}
</style>
</head>
<body>
<main>
<p class="kicker">GandiaTech CRM</p>
<h1>Este servidor usa una versión de PHP demasiado antigua</h1>
<div class="box">
PHP detectado: <strong>%1$s</strong> · versión necesaria: <strong>%2$s o superior</strong>.
</div>
<p>La aplicación todavía no se ha instalado porque el servidor no puede ejecutar su código.
No es un problema del paquete ni de los datos: solo hay que activar una versión de PHP más reciente.</p>
<ul>
<li><strong>Hosting con panel:</strong> busca la versión de PHP del dominio (suele estar en
<em>Hosting → PHP</em> o <em>Sitios web → Configuración → PHP</em>) y elige %2$s o superior.
Si no aparece esa opción, pide a tu proveedor que la active para el dominio.</li>
<li><strong>Servidor propio (Apache/Nginx):</strong> instala PHP %2$s y configúralo en el sitio.
En Debian/Ubuntu: <code>sudo apt install php%2$s</code>.</li>
<li><strong>Servidor embebido de PHP:</strong> comprueba la versión con <code>php -v</code> y
arranca con <code>php -S 127.0.0.1:8002 router.php</code> usando PHP %2$s o superior.</li>
</ul>
<p>Después de cambiarla, recarga esta página.</p>
<p><a class="btn" href="">Volver a comprobar</a></p>
</main>
</body>
</html>';

        printf($html, htmlspecialchars($current, ENT_QUOTES, 'UTF-8'), htmlspecialchars($minimum, ENT_QUOTES, 'UTF-8'));
        exit;
    }
}
