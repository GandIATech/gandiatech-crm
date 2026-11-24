<?php
declare(strict_types=1);

final class Database
{
    /** Versión del esquema base incluido en database/schema*.sql. */
    private const SCHEMA_BASELINE = 1;

    private static ?PDO $pdo = null;
    private static bool $initialized = false;
    private static string $driver = 'sqlite';

    public static function driver(): string
    {
        if (self::$pdo instanceof PDO) {
            return self::$driver;
        }
        $db = config('db');
        if (is_array($db) && ($db['driver'] ?? '') === 'mysql') {
            return 'mysql';
        }
        return 'sqlite';
    }

    public static function insertIgnoreKeyword(?string $driver = null): string
    {
        $driver ??= self::driver();
        return $driver === 'mysql' ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
    }

    /**
     * Conexión PDO a partir de la configuración de base de datos.
     */
    public static function connect(array $db): PDO
    {
        $driver = ($db['driver'] ?? 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if ($driver === 'mysql') {
            $host = (string) ($db['host'] ?? 'localhost');
            $port = (int) ($db['port'] ?? 3306);
            $database = (string) ($db['database'] ?? '');
            $charset = (string) ($db['charset'] ?? 'utf8mb4');
            $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $database . ';charset=' . $charset;
            $pdo = new PDO($dsn, (string) ($db['username'] ?? ''), (string) ($db['password'] ?? ''), $options);
            $pdo->exec('SET NAMES ' . $charset);
            return $pdo;
        }

        $path = (string) ($db['path'] ?? BASE_PATH . '/data/crm.sqlite');
        if ($path !== ':memory:' && !str_starts_with($path, '/')) {
            $path = BASE_PATH . DIRECTORY_SEPARATOR . ltrim($path, '/');
        }
        $dsn = str_starts_with($path, ':memory:') ? 'sqlite::memory:' : 'sqlite:' . $path;
        $pdo = new PDO($dsn, null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        if (!str_starts_with($path, ':memory:')) {
            $pdo->exec('PRAGMA journal_mode = WAL');
        }

        return $pdo;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $db = config('db');
        if (!is_array($db) || empty($db['driver'])) {
            $db = [
                'driver' => 'sqlite',
                'path' => (string) config('db_path', BASE_PATH . '/data/crm.sqlite'),
            ];
        }

        self::$driver = ($db['driver'] ?? 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';
        self::$pdo = self::connect($db);

        return self::$pdo;
    }

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        $pdo = self::pdo();
        if (!self::hasSchema($pdo, self::$driver)) {
            self::installSchema($pdo, self::$driver);
        }
        self::migrate();
        self::seed($pdo);

        $uploads = BASE_PATH . '/data/uploads';
        if (!is_dir($uploads)) {
            @mkdir($uploads, 0775, true);
        }
    }

    private static function hasSchema(PDO $pdo, string $driver): bool
    {
        try {
            if ($driver === 'mysql') {
                return $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn() !== false;
            }
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'users'");
            return $stmt->fetchColumn() !== false;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Crea el esquema completo. Se usa tanto en el arranque normal como en el
     * instalador, con una conexión ya validada.
     */
    public static function installSchema(PDO $pdo, string $driver): void
    {
        $file = BASE_PATH . '/database/' . ($driver === 'mysql' ? 'schema.mysql.sql' : 'schema.sql');
        $schema = file_get_contents($file);
        if ($schema === false) {
            throw new RuntimeException('No se pudo leer el esquema de base de datos.');
        }

        self::execSql($pdo, $schema, $driver);
    }

    /**
     * Migra la base de datos al arrancar: primero completa el esquema histórico
     * de forma idempotente y después aplica las migraciones numeradas.
     */
    private static function migrate(): void
    {
        $pdo = self::pdo();
        self::migrateLegacyColumns($pdo);
        self::applyVersionedMigrations($pdo);
    }

    /**
     * Añade columnas e índices nuevas a bases de datos anteriores al sistema
     * de migraciones. Es idempotente y se mantiene como red de seguridad.
     */
    private static function migrateLegacyColumns(PDO $pdo): void
    {
        $columns = [
            'users' => [
                'is_director' => 'INTEGER NOT NULL DEFAULT 0',
                'session_version' => 'INTEGER NOT NULL DEFAULT 1',
                'must_change_password' => 'INTEGER NOT NULL DEFAULT 0',
                'password_changed_at' => 'VARCHAR(32) NULL',
            ],
            'contacts' => ['deleted_at' => 'VARCHAR(32) NULL'],
            'leads' => ['lost_reason' => 'TEXT NULL', 'deleted_at' => 'VARCHAR(32) NULL'],
            'projects' => ['budgeted_hours' => 'DOUBLE NOT NULL DEFAULT 0', 'hourly_rate' => 'DOUBLE NOT NULL DEFAULT 0', 'deleted_at' => 'VARCHAR(32) NULL'],
            'tasks' => ['deleted_at' => 'VARCHAR(32) NULL'],
            'activities' => ['interaction_type' => 'VARCHAR(40) NULL', 'activity_date' => 'VARCHAR(20) NULL'],
        ];

        $pdo = self::pdo();

        if (self::$driver === 'mysql') {
            foreach ($columns as $table => $definitions) {
                $stmt = $pdo->prepare(
                    'SELECT COLUMN_NAME FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
                );
                foreach ($definitions as $name => $definition) {
                    $stmt->execute([$table, $name]);
                    if ($stmt->fetchColumn() === false) {
                        $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $name . '` ' . $definition);
                    }
                }
            }
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS login_attempts ('
                . 'id INT UNSIGNED NOT NULL AUTO_INCREMENT, email VARCHAR(190) NOT NULL, '
                . 'ip VARCHAR(45) NOT NULL, attempted_at VARCHAR(32) NOT NULL, '
                . 'PRIMARY KEY (id), KEY idx_login_attempts_email (email, ip, attempted_at), '
                . 'KEY idx_login_attempts_ip (ip, attempted_at)'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            return;
        }

        foreach ($columns as $table => $definitions) {
            $existing = [];
            foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll() as $column) {
                $existing[$column['name']] = true;
            }
            foreach ($definitions as $name => $definition) {
                if (!isset($existing[$name])) {
                    $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $name . ' ' . $definition);
                }
            }
        }

        foreach (['leads', 'contacts', 'projects', 'tasks'] as $table) {
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_' . $table . '_deleted ON ' . $table . '(deleted_at)');
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS login_attempts ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'email TEXT NOT NULL, ip TEXT NOT NULL, attempted_at TEXT NOT NULL)'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_login_attempts_email ON login_attempts(email, ip, attempted_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip, attempted_at)');
    }

    private static function ensureMigrationsTable(PDO $pdo): void
    {
        if (self::$driver === 'mysql') {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS schema_migrations ('
                . 'version INT UNSIGNED NOT NULL, applied_at VARCHAR(32) NOT NULL, '
                . 'PRIMARY KEY (version)'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            return;
        }
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations ('
            . 'version INTEGER PRIMARY KEY, applied_at TEXT NOT NULL)'
        );
    }

    /**
     * Aplica en orden los archivos database/migrations/NNN_descripcion.sql que
     * todavía no estén registrados en schema_migrations.
     */
    private static function applyVersionedMigrations(PDO $pdo): void
    {
        self::ensureMigrationsTable($pdo);

        $applied = array_map('intval', $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN));
        if ($applied === []) {
            // El esquema base ya existe (instalación nueva o anterior a las migraciones).
            $pdo->prepare(
                self::insertIgnoreKeyword() . ' INTO schema_migrations (version, applied_at) VALUES (?, ?)'
            )->execute([self::SCHEMA_BASELINE, now_utc()]);
            $applied = [self::SCHEMA_BASELINE];
        }

        $files = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
        sort($files, SORT_STRING);

        foreach ($files as $file) {
            $name = basename($file);
            if (preg_match('/^(\d{3,})_[A-Za-z0-9._-]+\.sql$/', $name, $matches) !== 1) {
                continue;
            }
            $version = (int) $matches[1];
            if ($version <= 0 || in_array($version, $applied, true)) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException('No se pudo leer la migración ' . $name . '.');
            }

            // MySQL hace commit implícito en DDL, así que allí no se usa transacción.
            $useTransaction = self::$driver !== 'mysql';
            if ($useTransaction) {
                $pdo->beginTransaction();
            }
            try {
                self::execSql($pdo, $sql, self::$driver);
                $pdo->prepare(
                    self::insertIgnoreKeyword() . ' INTO schema_migrations (version, applied_at) VALUES (?, ?)'
                )->execute([$version, now_utc()]);
                if ($useTransaction) {
                    $pdo->commit();
                }
            } catch (Throwable $e) {
                if ($useTransaction && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw new RuntimeException('Falló la migración ' . $name . ': ' . $e->getMessage(), 0, $e);
            }
            $applied[] = $version;
        }
    }

    /**
     * Ejecuta uno o varios statements. MySQL no admite varios en un exec().
     */
    private static function execSql(PDO $pdo, string $sql, string $driver): void
    {
        if ($driver !== 'mysql') {
            $pdo->exec($sql);
            return;
        }

        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        foreach (preg_split('/;\s*[\r\n]+/', $sql) ?: [] as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
    }

    /**
     * Última versión de esquema aplicada. 1 es el esquema base inicial.
     */
    public static function schemaVersion(): int
    {
        try {
            $pdo = self::pdo();
            self::ensureMigrationsTable($pdo);
            return (int) $pdo->query('SELECT COALESCE(MAX(version), 0) FROM schema_migrations')->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Datos iniciales. El instalador puede personalizar el administrador y el
     * nombre de la empresa; el arranque normal conserva los valores antiguos.
     */
    public static function seed(PDO $pdo, ?array $admin = null, array $settingsOverride = [], ?string $driver = null): void
    {
        $driver ??= self::driver();
        $statuses = [
            ['Nuevo', '#5E6472', 10, 0, 0],
            ['Contactado', '#2563EB', 20, 0, 0],
            ['Cualificado', '#7C3AED', 30, 0, 0],
            ['Propuesta', '#F59E0B', 40, 0, 0],
            ['Negociación', '#FF5A36', 50, 0, 0],
            ['Ganado', '#10B981', 60, 1, 0],
            ['Perdido', '#6B7280', 70, 0, 1],
        ];
        $insertStatus = $pdo->prepare(
            self::insertIgnoreKeyword($driver) . ' INTO lead_statuses (name, color, position, is_won, is_lost, created_at) VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($statuses as $status) {
            $insertStatus->execute([...$status, now_utc()]);
        }

        $settings = array_merge([
            'app_name' => 'GandiaTech CRM',
            'notifications_enabled' => '1',
            'mail_host' => '',
            'mail_port' => '587',
            'mail_username' => '',
            'mail_password' => '',
            'mail_encryption' => 'tls',
            'mail_from_email' => '',
            'mail_from_name' => '',
        ], $settingsOverride);
        $insertSetting = $pdo->prepare(
            self::insertIgnoreKeyword($driver) . ' INTO settings (`key`, `value`, updated_at) VALUES (?, ?, ?)'
        );
        foreach ($settings as $key => $value) {
            $insertSetting->execute([$key, $value, now_utc()]);
        }

        self::seed_task_templates($pdo, $driver);

        $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count === 0) {
            $admin ??= [
                'name' => 'Administrador',
                'email' => 'admin@gandia.tech',
                'password' => 'admin1234',
            ];
            $passwordHash = $admin['password_hash']
                ?? Security::hashPassword((string) ($admin['password'] ?? ''));
            $forcedChange = isset($admin['must_change_password']) ? (int) $admin['must_change_password'] : 1;
            $stmt = $pdo->prepare(
                'INSERT INTO users (name, email, password_hash, role, is_active, avatar_color, must_change_password, created_at)
                 VALUES (?, ?, ?, ?, 1, ?, ?, ?)'
            );
            $stmt->execute([
                $admin['name'],
                $admin['email'],
                $passwordHash,
                'admin',
                '#FF5A36',
                $forcedChange,
                now_utc(),
            ]);
        }
    }

    private static function seed_task_templates(PDO $pdo, string $driver): void
    {
        $templates = [
            'Desarrollo Web' => [
                'description' => 'Kick-off, diseño, desarrollo y entrega de un proyecto web.',
                'items' => [
                    ['Kick-off con el cliente', 'Reunión inicial para validar alcance, objetivos y calendario.', 'alta', 0],
                    ['Arquitectura y wireframes', 'Definir estructura de páginas, navegación y flujos principales.', 'alta', 3],
                    ['Diseño UI', 'Diseño visual de las pantallas acordadas.', 'media', 7],
                    ['Desarrollo frontend', 'Maquetación e implementación de la interfaz.', 'media', 14],
                    ['Desarrollo backend e integraciones', 'Lógica de negocio, formularios y servicios externos.', 'alta', 14],
                    ['Control de calidad', 'Revisión funcional, responsive y navegadores.', 'media', 21],
                    ['Formación y entrega', 'Sesión de formación y traspaso de documentación.', 'baja', 28],
                ],
            ],
            'Mantenimiento' => [
                'description' => 'Tareas recurrentes de soporte y evolución.',
                'items' => [
                    ['Revisión de incidencias', 'Revisar y priorizar las incidencias abiertas.', 'alta', 0],
                    ['Actualizaciones de seguridad', 'Aplicar parches y actualizaciones pendientes.', 'alta', 1],
                    ['Copia de seguridad y verificación', 'Comprobar copias y restauración del servicio.', 'media', 1],
                    ['Informe mensual', 'Resumen de actividad, incidencias y mejoras.', 'baja', 5],
                ],
            ],
            'Consultoría' => [
                'description' => 'Análisis, recomendaciones y acompañamiento.',
                'items' => [
                    ['Análisis inicial', 'Recopilar información y entender la situación actual.', 'alta', 0],
                    ['Entrevistas con el equipo', 'Sesiones con las personas implicadas.', 'media', 3],
                    ['Informe de recomendaciones', 'Documento con hallazgos y plan de acción.', 'alta', 7],
                    ['Presentación de resultados', 'Sesión de cierre y siguientes pasos.', 'media', 10],
                ],
            ],
        ];

        $insertTemplate = $pdo->prepare(
            self::insertIgnoreKeyword($driver) . ' INTO task_templates (name, description, created_at) VALUES (?, ?, ?)'
        );
        $findTemplate = $pdo->prepare('SELECT id FROM task_templates WHERE name = ?');
        $countItems = $pdo->prepare('SELECT COUNT(*) FROM task_template_items WHERE template_id = ?');
        $insertItem = $pdo->prepare(
            'INSERT INTO task_template_items (template_id, title, description, priority, offset_days, position) VALUES (?, ?, ?, ?, ?, ?)'
        );

        foreach ($templates as $name => $template) {
            $insertTemplate->execute([$name, $template['description'], now_utc()]);
            $findTemplate->execute([$name]);
            $id = (int) $findTemplate->fetchColumn();
            if ($id <= 0) {
                continue;
            }
            $countItems->execute([$id]);
            if ((int) $countItems->fetchColumn() > 0) {
                continue;
            }
            $position = 0;
            foreach ($template['items'] as [$title, $itemDescription, $priority, $offset]) {
                $insertItem->execute([$id, $title, $itemDescription, $priority, $offset, $position += 10]);
            }
        }
    }
}
