<?php
declare(strict_types=1);

final class Auth
{
    private static ?array $user = null;
    private static bool $resolved = false;

    /** Hash de referencia para igualar el coste cuando el correo no existe. */
    private const DUMMY_PASSWORD_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', '3600');

        $secure = Security::isHttps();
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'secure' => $secure,
            'samesite' => 'Lax',
        ]);
        session_name('gandiatech_crm');
        session_start();
    }

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;
        self::start();

        $id = $_SESSION['user_id'] ?? null;
        if (!$id) {
            return null;
        }

        $now = time();
        $lastActivity = (int) ($_SESSION['last_activity'] ?? 0);
        $startedAt = (int) ($_SESSION['started_at'] ?? 0);
        if (($lastActivity > 0 && $now - $lastActivity > Security::SESSION_IDLE_SECONDS)
            || ($startedAt > 0 && $now - $startedAt > Security::SESSION_ABSOLUTE_SECONDS)) {
            self::logout();
            return null;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT id, name, email, role, is_director, avatar_color, is_active, must_change_password,
                    session_version, last_login_at, created_at
             FROM users WHERE id = ?'
        );
        $stmt->execute([(int) $id]);
        $user = $stmt->fetch();
        if (!$user || !(int) $user['is_active']) {
            self::logout();
            return null;
        }

        $version = (int) ($user['session_version'] ?? 1);
        $sessionVersion = $_SESSION['session_version'] ?? null;
        if ($sessionVersion !== null && (int) $sessionVersion !== $version) {
            self::logout();
            return null;
        }
        if ($sessionVersion === null) {
            $_SESSION['session_version'] = $version;
        }

        $_SESSION['last_activity'] = $now;
        if ($startedAt === 0) {
            $_SESSION['started_at'] = $now;
        }

        self::$user = $user;
        return $user;
    }

    public static function requireUser(): array
    {
        $user = self::user();
        if (!$user) {
            throw new HttpError('Tu sesión ha caducado por inactividad. Vuelve a iniciar sesión.', 401);
        }
        return $user;
    }

    public static function requireAdmin(): array
    {
        $user = self::requireUser();
        if (($user['role'] ?? '') !== 'admin') {
            throw new HttpError('No tienes permisos para esta acción.', 403);
        }
        return $user;
    }

    public static function login(string $email, string $password): array
    {
        self::start();
        $email = text_lower(trim($email));

        if ($email === '' || $password === '' || strlen($password) > Security::PASSWORD_MAX_LENGTH) {
            throw new HttpError('Correo o contraseña incorrectos.', 401);
        }

        $ip = Security::clientIp();
        Security::assertLoginAllowed($email, $ip);

        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE email = ?' . sql_nocase() . ' LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        $verified = false;
        $hash = $user['password_hash'] ?? self::DUMMY_PASSWORD_HASH;
        if (password_verify($password, (string) $hash)) {
            $verified = true;
        }

        if (!$user || !$verified || !(int) $user['is_active']) {
            Security::recordLoginFailure($email, $ip);
            throw new HttpError('Correo o contraseña incorrectos.', 401);
        }

        if (Security::passwordNeedsRehash((string) $user['password_hash'])) {
            $newHash = Security::hashPassword($password);
            $update = Database::pdo()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $update->execute([$newHash, $user['id']]);
            $user['password_hash'] = $newHash;
        }

        // Una contraseña que ya no cumple la política se marca como temporal.
        if ((int) ($user['must_change_password'] ?? 0) !== 1
            && Security::passwordPolicyError($password, (string) $user['email'], (string) $user['name']) !== null) {
            Database::pdo()->prepare('UPDATE users SET must_change_password = 1 WHERE id = ?')
                ->execute([$user['id']]);
            $user['must_change_password'] = 1;
        }

        Security::clearLoginFailures($email, $ip);

        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $_SESSION['last_activity'] = time();
        $_SESSION['started_at'] = time();
        $_SESSION['session_version'] = (int) ($user['session_version'] ?? 1);

        Database::pdo()->prepare('UPDATE users SET last_login_at = ? WHERE id = ?')
            ->execute([now_utc(), $user['id']]);

        self::$resolved = false;
        self::$user = null;
        return self::publicUser(self::requireUser());
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'] ?? '',
                    'secure' => (bool) $params['secure'],
                    'httponly' => (bool) $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }
        session_destroy();
        self::$resolved = true;
        self::$user = null;
    }

    public static function csrf(): string
    {
        self::start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function checkCsrf(): void
    {
        self::start();
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($token) || $token === '' || empty($_SESSION['csrf'])
            || !hash_equals((string) $_SESSION['csrf'], $token)) {
            throw new HttpError('La sesión ha expirado. Recarga la página e inténtalo de nuevo.', 419);
        }
    }

    public static function publicUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'is_director' => (int) ($user['is_director'] ?? 0),
            'avatar_color' => $user['avatar_color'],
            'must_change_password' => (int) ($user['must_change_password'] ?? 0),
            'last_login_at' => $user['last_login_at'],
            'created_at' => $user['created_at'],
        ];
    }
}
