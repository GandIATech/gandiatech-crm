<?php
declare(strict_types=1);

/**
 * Comprobación del entorno antes de instalar.
 *
 * El asistente tiene que funcionar igual con el servidor embebido de PHP
 * (`php -S`), con Apache en la raíz de un dominio o con Apache/Nginx en una
 * subcarpeta. Estas comprobaciones no dan nada por supuesto: miden la versión
 * real de PHP, las extensiones cargadas, los permisos de escritura efectivos y
 * cómo se está publicando la aplicación, y devuelven instrucciones concretas
 * para corregir lo que falte.
 */
final class Environment
{
    /** Versión mínima de PHP que necesita la aplicación. */
    public const MIN_PHP = '8.2';

    /**
     * Extensiones sin las que la aplicación falla en funciones básicas.
     * (mbstring y sodium tienen alternativas en el código: van como aviso.)
     */
    private const REQUIRED_EXTENSIONS = [
        'pdo' => 'Acceso a la base de datos',
        'openssl' => 'Cifrado de credenciales y conexiones',
        'fileinfo' => 'Validación de los archivos que se suben',
        'session' => 'Sesiones de usuario',
        'json' => 'Respuestas de la API',
    ];

    /** Extensiones que mejoran la experiencia pero no impiden instalar. */
    private const OPTIONAL_EXTENSIONS = [
        'mbstring' => 'Acentos y mayúsculas correctos en nombres, búsquedas y correos',
        'sodium' => 'Verificación de la firma de las actualizaciones',
        'curl' => 'Descargar las actualizaciones desde el panel',
        'zip' => 'Descomprimir los paquetes de actualización',
        'pdo_sqlite' => 'Base de datos SQLite (opción por defecto)',
        'pdo_mysql' => 'Base de datos MySQL o MariaDB',
    ];

    /**
     * Informe completo del entorno.
     *
     * @return array{checks: list<array<string, string>>, errors: int, warnings: int, ok: int, blocking: bool, context: array<string, string>}
     */
    public static function report(): array
    {
        $checks = array_merge(
            self::phpChecks(),
            self::extensionChecks(),
            self::databaseChecks(),
            self::permissionChecks(),
            self::runtimeChecks(),
            self::webChecks(),
            self::packageChecks()
        );

        $counts = ['ok' => 0, 'warning' => 0, 'error' => 0];
        foreach ($checks as $check) {
            $counts[$check['status']] = ($counts[$check['status']] ?? 0) + 1;
        }

        return [
            'checks' => $checks,
            'errors' => $counts['error'],
            'warnings' => $counts['warning'],
            'ok' => $counts['ok'],
            'blocking' => $counts['error'] > 0,
            'context' => self::context(),
        ];
    }

    /**
     * Controladores PDO utilizables. El asistente solo ofrece los disponibles.
     *
     * @return array{sqlite: bool, mysql: bool}
     */
    public static function databaseDrivers(): array
    {
        $drivers = extension_loaded('pdo') ? PDO::getAvailableDrivers() : [];
        return [
            'sqlite' => in_array('sqlite', $drivers, true),
            'mysql' => in_array('mysql', $drivers, true),
        ];
    }

    /** Agrupa las comprobaciones para mostrarlas por secciones. */
    public static function groups(array $checks): array
    {
        $groups = [];
        foreach ($checks as $check) {
            $groups[$check['group']][] = $check;
        }
        return $groups;
    }

    /** Datos técnicos útiles para soporte y para los mensajes del asistente. */
    public static function context(): array
    {
        $scheme = self::isHttps() ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $base = function_exists('crm_base_path') ? crm_base_path() : self::guessBasePath();
        $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $appRoot = realpath(BASE_PATH);

        return [
            'sapi' => PHP_SAPI,
            'php' => PHP_VERSION,
            'server' => self::serverLabel(),
            'host' => $host,
            'base_path' => $base === '' ? '/' : $base . '/',
            'url' => $host === '' ? '' : $scheme . '://' . $host . ($base === '' ? '' : $base) . '/',
            'https' => self::isHttps() ? 'sí' : 'no',
            'document_root' => (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''),
            'app_root' => $appRoot !== false ? $appRoot : BASE_PATH,
            'document_root_mismatch' => $documentRoot !== false && $appRoot !== false && $documentRoot !== $appRoot
                ? 'sí' : 'no',
            'local' => self::isLocal() ? 'sí' : 'no',
        ];
    }

    private static function check(string $group, string $id, string $label, string $status, string $detail = '', string $fix = ''): array
    {
        return [
            'group' => $group,
            'id' => $id,
            'label' => $label,
            'status' => $status,
            'detail' => $detail,
            'fix' => $fix,
        ];
    }

    /** @return list<array<string, string>> */
    private static function phpChecks(): array
    {
        $status = version_compare(PHP_VERSION, self::MIN_PHP, '>=') ? 'ok' : 'error';

        return [
            self::check(
                'Servidor y PHP',
                'php',
                'PHP ' . self::MIN_PHP . ' o superior',
                $status,
                'Versión detectada: PHP ' . PHP_VERSION . ' (' . PHP_SAPI . ').',
                'Cambia la versión de PHP del dominio desde el panel del hosting (en IONOS: Hosting → PHP; en otros paneles suele estar en «Sitios web» o «Configuración PHP»). Si usas tu propio servidor, instala PHP ' . self::MIN_PHP . ' y selecciónalo en el sitio. Si no puedes cambiarla, pide a tu proveedor que la active para el dominio.'
            ),
            self::check(
                'Servidor y PHP',
                'sapi',
                'Ejecución de scripts',
                in_array(PHP_SAPI, ['apache2handler', 'fpm-fcgi', 'cgi-fcgi', 'cli-server', 'cli'], true) ? 'ok' : 'warning',
                'Interfaz de ejecución: ' . PHP_SAPI . '.',
                'La aplicación está pensada para Apache, Nginx con PHP-FPM o el servidor embebido de PHP. Si tu hosting usa otro modo, comprueba con el proveedor que se ejecuta PHP en este dominio.'
            ),
        ];
    }

    /** @return list<array<string, string>> */
    private static function extensionChecks(): array
    {
        $checks = [];
        foreach (self::REQUIRED_EXTENSIONS as $extension => $purpose) {
            $loaded = extension_loaded($extension);
            $checks[] = self::check(
                'Extensiones de PHP',
                'ext-' . $extension,
                'Extensión ' . $extension,
                $loaded ? 'ok' : 'error',
                $loaded ? 'Cargada. ' . $purpose . '.' : 'No está activa. ' . $purpose . '.',
                $loaded ? '' : 'Activa la extensión «' . $extension . '» en el panel del hosting o en tu php.ini (en Debian/Ubuntu: sudo apt install php' . self::MIN_PHP . '-' . $extension . '). Si no tienes acceso, pide al proveedor que la active.'
            );
        }

        foreach (self::OPTIONAL_EXTENSIONS as $extension => $purpose) {
            if (extension_loaded($extension)) {
                continue;
            }
            $checks[] = self::check(
                'Extensiones de PHP',
                'ext-' . $extension,
                'Extensión ' . $extension . ' (opcional)',
                'warning',
                'No está activa. ' . $purpose . '.',
                'Puedes instalar el CRM sin ella, pero se pierde esa función. Actívala en el panel o en php.ini si la necesitas.'
            );
        }

        return $checks;
    }

    /** @return list<array<string, string>> */
    private static function databaseChecks(): array
    {
        $drivers = self::databaseDrivers();
        $available = array_keys(array_filter($drivers));

        if ($available === []) {
            return [
                self::check(
                    'Base de datos',
                    'db-driver',
                    'Controlador de base de datos',
                    'error',
                    'No hay ningún controlador PDO de SQLite ni de MySQL disponible.',
                    'Activa «pdo_sqlite» (lo más sencillo: no necesita servidor) o «pdo_mysql» en el panel del hosting. La aplicación no puede guardar datos sin uno de los dos.'
                ),
            ];
        }

        $names = [];
        if ($drivers['sqlite']) {
            $names[] = 'SQLite';
        }
        if ($drivers['mysql']) {
            $names[] = 'MySQL/MariaDB';
        }

        return [
            self::check(
                'Base de datos',
                'db-driver',
                'Controlador de base de datos',
                'ok',
                'Disponible: ' . implode(' y ', $names) . '.',
                'Puedes elegir cualquiera de ellos en el siguiente paso.'
            ),
            self::check(
                'Base de datos',
                'db-sqlite-dir',
                'Carpeta de la base de datos',
                self::probeDirectory(BASE_PATH . '/data')['status'],
                self::probeDirectory(BASE_PATH . '/data')['detail'],
                'La base de datos SQLite se guarda en data/. Da permisos de escritura a esa carpeta para el usuario de PHP.'
            ),
        ];
    }

    /** @return list<array<string, string>> */
    private static function permissionChecks(): array
    {
        $targets = [
            [BASE_PATH . '/config', 'config/', 'Guardar la configuración al terminar'],
            [BASE_PATH . '/data', 'data/', 'Base de datos, copias de seguridad y registros'],
            [BASE_PATH . '/data/uploads', 'data/uploads/', 'Adjuntos y fotos que se suben al CRM'],
        ];

        $checks = [];
        foreach ($targets as [$path, $label, $purpose]) {
            $probe = self::probeDirectory($path);
            $checks[] = self::check(
                'Permisos de escritura',
                'perm-' . trim($label, '/'),
                'Escritura en ' . $label,
                $probe['status'],
                $probe['detail'] . ' ' . $purpose . '.',
                $probe['status'] === 'ok' ? '' : 'Da permisos de escritura al usuario que ejecuta PHP: en el gestor de archivos o por FTP pon chmod 755 en la carpeta y asegúrate de que es del mismo usuario que PHP. En local: chmod -R u+rwX config data. Si el hosting lo permite, 775 también sirve.'
            );
        }

        $root = self::probeDirectory(BASE_PATH, false);
        $checks[] = self::check(
            'Permisos de escritura',
            'perm-install-removal',
            'Eliminar install/ al terminar',
            $root['status'] === 'ok' ? 'ok' : 'warning',
            $root['status'] === 'ok'
                ? 'La carpeta del CRM permite borrar install/ automáticamente.'
                : 'La carpeta del CRM no permite escritura, así que install/ no podrá borrarse solo.',
            'La instalación funciona igualmente: al terminar tendrás que borrar la carpeta install/ a mano desde el gestor de archivos o por FTP.'
        );

        return $checks;
    }

    /** @return list<array<string, string>> */
    private static function runtimeChecks(): array
    {
        $checks = [];

        $sessionPath = (string) ini_get('session.save_path');
        $sessionHandler = (string) ini_get('session.save_handler');
        $sessionOk = session_status() === PHP_SESSION_ACTIVE;
        $checks[] = self::check(
            'Configuración de PHP',
            'session',
            'Sesiones',
            $sessionOk ? 'ok' : 'warning',
            $sessionOk
                ? 'La sesión de instalación se ha iniciado correctamente.'
                : 'No se pudo comprobar una sesión activa (manejador: ' . ($sessionHandler !== '' ? $sessionHandler : 'files') . ').',
            $sessionOk ? '' : 'Si al continuar la instalación pierdes el progreso, revisa que la carpeta de sesiones de PHP (' . ($sessionPath !== '' ? $sessionPath : 'session.save_path') . ') exista y sea escribible.'
        );

        $checks[] = self::check(
            'Configuración de PHP',
            'file_uploads',
            'Subida de archivos',
            filter_var(ini_get('file_uploads'), FILTER_VALIDATE_BOOLEAN) ? 'ok' : 'warning',
            'file_uploads = ' . (ini_get('file_uploads') ?: '0') . '.',
            'Sin subidas activadas no se podrán adjuntar documentos ni fotos. Actívalo en php.ini (file_uploads = On).'
        );

        $uploadMax = self::iniBytes((string) ini_get('upload_max_filesize'));
        $postMax = self::iniBytes((string) ini_get('post_max_size'));
        $checks[] = self::check(
            'Configuración de PHP',
            'upload_limits',
            'Tamaño máximo de adjuntos',
            $uploadMax >= 2 * 1024 * 1024 ? 'ok' : 'warning',
            'upload_max_filesize = ' . ini_get('upload_max_filesize') . ' · post_max_size = ' . ini_get('post_max_size') . '.',
            'Con menos de 2 MB apenas se pueden adjuntar documentos. Sube upload_max_filesize y post_max_size a 8M o más en php.ini o en el panel del hosting.'
        );
        if ($postMax > 0 && $postMax < 8 * 1024 * 1024) {
            $checks[] = self::check(
                'Configuración de PHP',
                'post_max_size',
                'Tamaño máximo de formularios',
                'warning',
                'post_max_size = ' . ini_get('post_max_size') . '.',
                'Debe ser igual o mayor que upload_max_filesize; con 8M o 16M hay margen suficiente.'
            );
        }

        $memory = self::iniBytes((string) ini_get('memory_limit'));
        $checks[] = self::check(
            'Configuración de PHP',
            'memory_limit',
            'Memoria disponible',
            $memory === -1 || $memory >= 64 * 1024 * 1024 ? 'ok' : 'warning',
            'memory_limit = ' . ini_get('memory_limit') . '.',
            'La aplicación funciona con 64M o más. Si ves errores de memoria al exportar, sube memory_limit en php.ini.'
        );

        return $checks;
    }

    /** @return list<array<string, string>> */
    private static function webChecks(): array
    {
        $checks = [];
        $context = self::context();
        $server = $context['server'];
        $hasHtaccess = is_file(BASE_PATH . '/.htaccess');

        $detail = $server . ' · ' . ($context['url'] !== '' ? $context['url'] : 'URL no detectada');
        if ($context['document_root_mismatch'] === 'sí') {
            $detail .= ' · La raíz del documento (' . $context['document_root'] . ') no coincide con la carpeta del CRM ('
                . $context['app_root'] . '); no es un problema, las rutas se resuelven con PHP.';
        }
        $checks[] = self::check('Publicación web', 'context', 'Contexto detectado', 'ok', $detail, '');

        if (PHP_SAPI === 'cli-server') {
            // El servidor embebido no aplica .htaccess: las rutas /api y /app
            // dependen del router incluido en el paquete.
            $checks[] = self::check(
                'Publicación web',
                'rewrite',
                'Reescritura de URLs',
                'ok',
                'Servidor embebido de PHP (solo desarrollo y pruebas). Recuerda arrancarlo con el router del paquete para que /api y /app funcionen: php -S 127.0.0.1:8002 router.php.',
                ''
            );
        } elseif ($server === 'Apache') {
            $modules = function_exists('apache_get_modules') ? apache_get_modules() : null;
            $rewrite = $modules === null || in_array('mod_rewrite', $modules, true);
            $checks[] = self::check(
                'Publicación web',
                'rewrite',
                'Reescritura de URLs (mod_rewrite)',
                $rewrite ? 'ok' : 'error',
                $modules === null
                    ? 'No se puede verificar desde PHP; el .htaccess incluido activa mod_rewrite si el hosting permite AllowOverride.'
                    : ($rewrite ? 'mod_rewrite está activo.' : 'mod_rewrite no está activo.'),
                $rewrite ? '' : 'Activa mod_rewrite en Apache (a2enmod rewrite) o pide a tu proveedor que lo active para el dominio.'
            );
            $checks[] = self::check(
                'Publicación web',
                'htaccess',
                'Archivo .htaccess',
                $hasHtaccess ? 'ok' : 'error',
                $hasHtaccess ? 'Presente en la carpeta del CRM.' : 'No se encuentra .htaccess en la carpeta del CRM.',
                $hasHtaccess ? '' : 'Vuelve a subir el archivo .htaccess del paquete a la misma carpeta que index.php (algunos clientes FTP ocultan los archivos que empiezan por punto).'
            );
        } elseif ($server === 'Nginx') {
            $checks[] = self::check(
                'Publicación web',
                'rewrite',
                'Reescritura de URLs (Nginx)',
                'warning',
                'Nginx no lee los archivos .htaccess: las rutas /api e /install deben configurarse en el bloque del servidor.',
                'Copia las reglas de la sección «Nginx» de la guía de instalación (LEEME.md) en el bloque server del dominio.'
            );
        } else {
            $checks[] = self::check(
                'Publicación web',
                'rewrite',
                'Reescritura de URLs',
                'warning',
                'Servidor no reconocido: ' . $server . '.',
                'La aplicación necesita que /api y /install lleguen a los archivos PHP correspondientes. Revisa la guía de instalación para tu servidor.'
            );
        }

        $https = $context['https'] === 'sí';
        $local = $context['local'] === 'sí';
        $checks[] = self::check(
            'Publicación web',
            'https',
            'Conexión cifrada (HTTPS)',
            $https ? 'ok' : ($local ? 'warning' : 'warning'),
            $https ? 'La instalación se está ejecutando por HTTPS.' : ($local ? 'Entorno local sin HTTPS: es normal en desarrollo.' : 'La instalación se está ejecutando por HTTP sin cifrar.'),
            $https ? '' : ($local
                ? 'En producción activa HTTPS con un certificado (Let\'s Encrypt o el que incluya tu hosting) antes de usarlo con datos reales.'
                : 'Activa HTTPS y el certificado del dominio antes de usar el CRM: sin cifrado, las credenciales viajan en claro. En el panel del hosting suele haber una opción para activar el certificado SSL gratuito.')
        );

        return $checks;
    }

    /** @return list<array<string, string>> */
    private static function packageChecks(): array
    {
        $checks = [];

        $autoload = BASE_PATH . '/vendor/autoload.php';
        $checks[] = self::check(
            'Paquete de la aplicación',
            'vendor',
            'Dependencias (vendor/)',
            is_file($autoload) && is_readable($autoload) ? 'ok' : 'error',
            is_file($autoload) ? 'vendor/autoload.php presente.' : 'No se encuentra vendor/autoload.php.',
            'Sube el paquete completo: falta la carpeta vendor/. Si prefieres generarla, ejecuta composer install --no-dev en la carpeta del CRM.'
        );

        foreach (['sqlite' => 'schema.sql', 'mysql' => 'schema.mysql.sql'] as $driver => $file) {
            $path = BASE_PATH . '/database/' . $file;
            $enabled = self::databaseDrivers()[$driver];
            if (!$enabled) {
                continue;
            }
            $checks[] = self::check(
                'Paquete de la aplicación',
                'schema-' . $driver,
                'Esquema de base de datos (' . $driver . ')',
                is_file($path) && is_readable($path) ? 'ok' : 'error',
                'database/' . $file . (is_file($path) ? ' presente.' : ' no encontrado.'),
                'Vuelve a subir la carpeta database/ del paquete; sin el esquema no se pueden crear las tablas.'
            );
        }

        $appIndex = BASE_PATH . '/app/index.html';
        $checks[] = self::check(
            'Paquete de la aplicación',
            'frontend',
            'Interfaz web (app/)',
            is_file($appIndex) ? 'ok' : 'error',
            is_file($appIndex) ? 'app/index.html presente.' : 'No se encuentra app/index.html.',
            'Sube la carpeta app/ del paquete. Si la has subido y sigue sin aparecer, revisa que no esté dentro de otra carpeta.'
        );

        return $checks;
    }

    /**
     * Comprueba si PHP puede escribir en una carpeta creando un archivo temporal.
     *
     * @return array{status: string, detail: string}
     */
    private static function probeDirectory(string $path, bool $mustExist = true): array
    {
        if (!is_dir($path)) {
            $parent = dirname($path);
            if (!$mustExist && is_writable($parent)) {
                return ['status' => 'ok', 'detail' => self::shortPath($path) . ' se creará al instalar.'];
            }
            if (is_writable($parent)) {
                return ['status' => 'ok', 'detail' => self::shortPath($path) . ' no existe todavía, pero se puede crear.'];
            }
            return ['status' => 'error', 'detail' => 'No existe ' . self::shortPath($path) . ' y no se puede crear.'];
        }

        if (!is_writable($path)) {
            return ['status' => 'error', 'detail' => 'La carpeta ' . self::shortPath($path) . ' no es escribible (' . self::pathMeta($path) . ').'];
        }

        $probe = $path . '/.crm-write-test-' . bin2hex(random_bytes(6));
        if (@file_put_contents($probe, 'ok') === false) {
            return ['status' => 'error', 'detail' => 'No se pudo crear un archivo de prueba en ' . self::shortPath($path) . ' (' . self::pathMeta($path) . ').'];
        }
        @unlink($probe);

        return ['status' => 'ok', 'detail' => 'La carpeta ' . self::shortPath($path) . ' es escribible.'];
    }

    private static function shortPath(string $path): string
    {
        $base = rtrim(BASE_PATH, DIRECTORY_SEPARATOR);
        if (str_starts_with($path, $base)) {
            return ltrim(substr($path, strlen($base)), DIRECTORY_SEPARATOR) . '/';
        }
        return $path;
    }

    private static function pathMeta(string $path): string
    {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        $owner = 'usuario ' . (string) fileowner($path);
        if (function_exists('posix_getpwuid')) {
            $info = @posix_getpwuid((int) fileowner($path));
            if (is_array($info) && isset($info['name'])) {
                $owner = 'propietario ' . $info['name'];
            }
        }
        return $owner . ', permisos ' . $perms;
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return $value === '-1' ? -1 : 0;
        }
        $unit = strtolower(substr($value, -1));
        $number = (float) $value;
        return (int) match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private static function serverLabel(): string
    {
        if (PHP_SAPI === 'cli-server') {
            return 'Servidor embebido de PHP (php -S)';
        }
        $software = (string) ($_SERVER['SERVER_SOFTWARE'] ?? '');
        if (stripos($software, 'apache') !== false) {
            return 'Apache';
        }
        if (stripos($software, 'nginx') !== false) {
            return 'Nginx';
        }
        if (stripos($software, 'litespeed') !== false) {
            return 'LiteSpeed';
        }
        if (stripos($software, 'iis') !== false) {
            return 'IIS';
        }
        return $software !== '' ? $software : 'Desconocido';
    }

    private static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        $proto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        return $proto === 'https' || str_starts_with($proto, 'https,');
    }

    private static function isLocal(): bool
    {
        if (PHP_SAPI === 'cli-server' || PHP_SAPI === 'cli') {
            return true;
        }
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.test');
    }

    private static function guessBasePath(): string
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        $dir = (string) preg_replace('#/(?:api|install)$#', '', $dir);
        return ($dir === '' || $dir === '.' || $dir === '/') ? '' : $dir;
    }
}
