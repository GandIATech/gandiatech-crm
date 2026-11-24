<?php
declare(strict_types=1);

/**
 * Asistente de instalación para la versión distribuible del CRM.
 * Se ejecuta antes de que exista config/config.php, por lo que no usa
 * Database::pdo() hasta que la conexión ha sido validada.
 */
final class InstallerException extends RuntimeException
{
}

final class Installer
{
    public static function configPath(): string
    {
        return BASE_PATH . '/config/config.php';
    }

    public static function isInstalled(): bool
    {
        return app_installed();
    }

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        $secure = Security::isHttps();
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'secure' => $secure,
            'samesite' => 'Lax',
        ]);
        session_name('gandiatech_crm_install');
        session_start();
    }

    public static function csrf(): string
    {
        self::startSession();
        if (empty($_SESSION['install_csrf'])) {
            $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['install_csrf'];
    }

    public static function checkCsrf(): void
    {
        self::startSession();
        $token = (string) ($_POST['csrf'] ?? '');
        if ($token === '' || empty($_SESSION['install_csrf']) || !hash_equals((string) $_SESSION['install_csrf'], $token)) {
            throw new InstallerException('La sesión de instalación ha caducado. Recarga la página e inténtalo de nuevo.');
        }
    }

    public static function draft(): array
    {
        self::startSession();
        return is_array($_SESSION['install_draft'] ?? null) ? $_SESSION['install_draft'] : [];
    }

    public static function saveDraft(array $data): void
    {
        self::startSession();
        $_SESSION['install_draft'] = $data;
    }

    public static function clearDraft(): void
    {
        self::startSession();
        unset($_SESSION['install_draft']);
        unset($_SESSION['install_csrf']);
    }

    public static function defaultSqlitePath(): string
    {
        return 'data/crm.sqlite';
    }

    /**
     * Valida la conexión y devuelve la configuración normalizada de la BD.
     */
    public static function testDatabase(array $input): array
    {
        $driver = ($input['driver'] ?? 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';
        if ($driver === 'mysql') {
            return self::testMysql($input);
        }
        return self::testSqlite($input);
    }

    private static function testSqlite(array $input): array
    {
        $path = self::resolveSqlitePath((string) ($input['sqlite_path'] ?? self::defaultSqlitePath()));
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new InstallerException('No se pudo crear la carpeta de la base de datos: ' . $dir);
        }
        if (!is_writable($dir)) {
            throw new InstallerException('La carpeta de la base de datos no tiene permisos de escritura: ' . $dir);
        }

        try {
            $pdo = Database::connect(['driver' => 'sqlite', 'path' => $path]);
            $existing = (int) $pdo->query(
                "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
            )->fetchColumn();
        } catch (Throwable $e) {
            throw new InstallerException('No se pudo abrir la base de datos SQLite. Comprueba la ruta y los permisos.');
        }

        if ($existing > 0) {
            throw new InstallerException('Ese archivo SQLite ya contiene tablas. Elige otra ruta o elimina la base de datos anterior.');
        }

        // Guardamos una ruta relativa cuando sea posible para que el CRM se
        // pueda mover de carpeta sin romper la configuración.
        $base = realpath(BASE_PATH);
        $storedPath = $path;
        if ($base !== false && str_starts_with($path, $base . DIRECTORY_SEPARATOR)) {
            $storedPath = substr($path, strlen($base) + 1);
        }

        return ['driver' => 'sqlite', 'path' => $storedPath];
    }

    private static function testMysql(array $input): array
    {
        if (!extension_loaded('pdo_mysql')) {
            throw new InstallerException('Este servidor PHP no tiene activada la extensión pdo_mysql. Actívala o usa SQLite.');
        }

        $host = trim((string) ($input['mysql_host'] ?? 'localhost')) ?: 'localhost';
        $port = (int) ($input['mysql_port'] ?? 3306);
        $database = trim((string) ($input['mysql_database'] ?? ''));
        $username = trim((string) ($input['mysql_username'] ?? ''));
        $password = (string) ($input['mysql_password'] ?? '');

        if ($database === '' || $username === '') {
            throw new InstallerException('Indica el nombre de la base de datos y el usuario de MySQL.');
        }
        if (strlen($host) > 190 || preg_match('/^[a-zA-Z0-9.\-:\[\]]+$/', $host) !== 1) {
            throw new InstallerException('El servidor de MySQL no es válido.');
        }
        if (strlen($database) > 64 || preg_match('/^[a-zA-Z0-9_\-]+$/', $database) !== 1) {
            throw new InstallerException('El nombre de la base de datos no es válido.');
        }
        if (strlen($username) > 32 || preg_match('/^[a-zA-Z0-9_.\-]+$/', $username) !== 1) {
            throw new InstallerException('El usuario de MySQL no es válido.');
        }
        if (strlen($password) > 500) {
            throw new InstallerException('La contraseña de MySQL es demasiado larga.');
        }
        if ($port < 1 || $port > 65535) {
            throw new InstallerException('El puerto de MySQL no es válido.');
        }

        $config = [
            'driver' => 'mysql',
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset' => 'utf8mb4',
        ];

        try {
            $pdo = Database::connect($config);
            $pdo->query('SELECT 1');
            $exists = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
        } catch (Throwable $e) {
            throw new InstallerException('No se pudo conectar con MySQL. Revisa el servidor, el usuario y la base de datos.');
        }

        if ($exists !== false) {
            throw new InstallerException('La base de datos MySQL ya contiene una instalación del CRM.');
        }

        return $config;
    }

    public static function resolveSqlitePath(string $input): string
    {
        $input = trim($input);
        if ($input === '') {
            $input = self::defaultSqlitePath();
        }
        if (str_contains($input, "\0")) {
            throw new InstallerException('La ruta de SQLite no es válida.');
        }

        $base = realpath(BASE_PATH);
        if ($base === false) {
            throw new InstallerException('No se pudo resolver la carpeta del proyecto.');
        }

        $absolute = str_starts_with($input, '/') ? $input : $base . '/' . ltrim($input, '/');
        $directory = dirname($absolute);
        $name = basename($absolute);
        if ($name === '' || $name === '.' || $name === '..') {
            throw new InstallerException('La ruta de SQLite no es válida.');
        }
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new InstallerException('No se pudo crear la carpeta de SQLite.');
        }
        $realDirectory = realpath($directory);
        if ($realDirectory === false
            || ($realDirectory !== $base && !str_starts_with($realDirectory, $base . DIRECTORY_SEPARATOR))) {
            throw new InstallerException('Por seguridad, la base de datos SQLite debe estar dentro de la carpeta del CRM.');
        }

        return $realDirectory . DIRECTORY_SEPARATOR . $name;
    }

    /**
     * Crea el esquema, los datos iniciales y el archivo de configuración.
     */
    public static function install(array $db, array $admin, string $appName): void
    {
        if (self::isInstalled()) {
            throw new InstallerException('El CRM ya está instalado.');
        }

        $driver = ($db['driver'] ?? 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';
        $appName = trim($appName) !== '' ? trim($appName) : 'Mi CRM';

        $pdo = Database::connect($db);
        Database::installSchema($pdo, $driver);
        $admin['must_change_password'] = 0;
        Database::seed($pdo, $admin, [
            'app_name' => $appName,
            'notifications_enabled' => '1',
            'mail_host' => '',
            'mail_port' => '587',
            'mail_username' => '',
            'mail_password' => '',
            'mail_encryption' => 'tls',
            'mail_from_email' => '',
            'mail_from_name' => '',
        ], $driver);

        $uploads = BASE_PATH . '/data/uploads';
        if (!is_dir($uploads)) {
            @mkdir($uploads, 0775, true);
        }

        self::writeConfig($db);
    }

    /**
     * Elimina la carpeta del instalador. Se hace después de escribir la
     * configuración para que una instalación completada no pueda repetirse.
     */
    public static function removeInstallerDirectory(string $directory): bool
    {
        $directory = rtrim($directory, DIRECTORY_SEPARATOR);
        if (!is_dir($directory) || basename($directory) !== 'install') {
            return false;
        }
        return self::removeTree($directory);
    }

    private static function removeTree(string $directory): bool
    {
        $items = @scandir($directory);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path) && !is_link($path)) {
                self::removeTree($path);
            } else {
                @unlink($path);
            }
        }

        return @rmdir($directory);
    }

    private static function writeConfig(array $db): void
    {
        $file = self::configPath();
        if (is_file($file)) {
            throw new InstallerException('Ya existe config/config.php. Elimínalo si quieres reinstalar el CRM.');
        }
        if (!is_dir(dirname($file)) && !@mkdir(dirname($file), 0775, true) && !is_dir(dirname($file))) {
            throw new InstallerException('No se pudo crear la carpeta config/.');
        }

        $key = bin2hex(random_bytes(32));
        $appUrl = trim((string) getenv('CRM_APP_URL'));
        if ($appUrl === '') {
            $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
            if ($host !== '' && preg_match('/^[a-zA-Z0-9.\-:\[\]]{1,253}$/', $host) === 1) {
                $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
                $appUrl = ($https ? 'https' : 'http') . '://' . $host;
            }
        }
        $content = "<?php\nreturn [\n"
            . "    'app_key' => " . var_export($key, true) . ",\n"
            . "    'app_url' => " . var_export($appUrl, true) . ",\n"
            . "    'debug' => false,\n"
            . "    'installed' => true,\n"
            . "    'db' => " . var_export($db, true) . ",\n"
            . "];\n";

        if (file_put_contents($file, $content, LOCK_EX) === false) {
            throw new InstallerException('No se pudo escribir config/config.php. Revisa los permisos de la carpeta config/.');
        }
        @chmod($file, 0640);
    }
}
