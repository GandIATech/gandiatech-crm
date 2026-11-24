<?php
declare(strict_types=1);

/**
 * Reglas de seguridad compartidas por la API, el instalador y los routers.
 * Todo lo que afecta a contraseñas, sesiones, límites de acceso y cabeceras
 * vive aquí para que no haya políticas distintas según el punto de entrada.
 */
final class Security
{
    public const SESSION_IDLE_SECONDS = 300;
    public const SESSION_ABSOLUTE_SECONDS = 12 * 60 * 60;
    public const LOGIN_WINDOW_SECONDS = 15 * 60;
    public const LOGIN_MAX_PER_ACCOUNT = 5;
    public const LOGIN_MAX_PER_ACCOUNT_GLOBAL = 10;
    public const LOGIN_MAX_PER_IP = 20;
    public const PASSWORD_MIN_LENGTH = 12;
    public const PASSWORD_MAX_LENGTH = 72;

    /** Lista corta de contraseñas y patrones que nunca deben aceptarse. */
    private const COMMON_PASSWORDS = [
        '123456789012',
        '1234567890123',
        'admin1234',
        'administrador',
        'contraseña123',
        'gandiatech',
        'gandiatech123',
        'password1234',
        'qwerty123456',
        'crm123456789',
        'bienvenido123',
    ];

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }

    /**
     * Cabeceras comunes. La CSP permite los estilos y scripts inline que ya
     * usa el frontend compilado, pero bloquea orígenes externos no previstos.
     */
    public static function headers(): void
    {
        if (headers_sent()) {
            return;
        }
        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('X-Permitted-Cross-Domain-Policies: none');
        header('X-Robots-Tag: noindex, nofollow');
        header(
            "Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; "
            . "frame-ancestors 'none'; object-src 'none'; img-src 'self' data:; "
            . "font-src 'self' https://fonts.gstatic.com; "
            . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
            . "script-src 'self' 'unsafe-inline'; connect-src 'self'"
        );
        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }

    /**
     * Comprueba que una petición de escritura procede del mismo origen.
     * El token CSRF sigue siendo obligatorio para usuarios autenticados; esto
     * añade una barrera extra frente a formularios cruzados y login CSRF.
     */
    public static function checkSameOrigin(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $source = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
        if ($source === '') {
            $source = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
        }
        if ($source === '') {
            return;
        }

        $sourceHost = parse_url($source, PHP_URL_HOST);
        $sourcePort = parse_url($source, PHP_URL_PORT);
        $sourceScheme = strtolower((string) parse_url($source, PHP_URL_SCHEME));
        $hostHeader = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $hostParts = parse_url('//' . $hostHeader);
        $host = $hostParts['host'] ?? '';
        $port = $hostParts['port'] ?? null;

        $sameHost = is_string($sourceHost) && $sourceHost !== '' && strcasecmp($sourceHost, (string) $host) === 0;
        $defaultPort = $sourceScheme === 'https' ? 443 : 80;
        $samePort = ($sourcePort ?? $defaultPort) === ($port ?? $defaultPort);
        $sameScheme = $sourceScheme === '' || $sourceScheme === (self::isHttps() ? 'https' : 'http');

        if (!$sameHost || !$samePort || !$sameScheme) {
            throw new HttpError('Origen de la petición no válido.', 403);
        }
    }

    public static function clientIp(): string
    {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'desconocida';
    }

    /**
     * Devuelve el error de política o null si la contraseña es aceptable.
     * No se recorta ni se normaliza: los espacios forman parte del secreto.
     */
    /** Argon2id cuando el servidor lo soporta; bcrypt como alternativa. */
    public static function passwordAlgorithm(): string|int
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, self::passwordAlgorithm());
    }

    public static function passwordNeedsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::passwordAlgorithm());
    }

    public static function passwordPolicyError(string $password, string $email = '', string $name = ''): ?string
    {
        $length = text_length($password);
        if ($length === 0) {
            return 'Introduce una contraseña.';
        }
        if ($length < self::PASSWORD_MIN_LENGTH) {
            return 'La contraseña debe tener al menos ' . self::PASSWORD_MIN_LENGTH . ' caracteres.';
        }
        if (strlen($password) > self::PASSWORD_MAX_LENGTH) {
            return 'La contraseña no puede superar los ' . self::PASSWORD_MAX_LENGTH . ' caracteres.';
        }
        if (preg_match('/\s{2,}/u', $password) === 1) {
            return 'La contraseña no puede contener espacios consecutivos.';
        }

        $lower = text_lower($password);
        if (!preg_match('/[a-záéíóúüñ]/iu', $password) || !preg_match('/\d/', $password)) {
            return 'La contraseña debe incluir letras y al menos un número.';
        }
        if (in_array($lower, self::COMMON_PASSWORDS, true)
            || preg_match('/^(?:123456|password|qwerty|admin123|gandiatech|crm123)/i', $lower) === 1) {
            return 'Esa contraseña es demasiado predecible. Elige otra distinta.';
        }

        $email = text_lower(trim($email));
        if ($email !== '') {
            $local = strstr($email, '@', true);
            if (is_string($local) && text_length($local) >= 3 && str_contains($lower, $local)) {
                return 'La contraseña no puede contener tu correo electrónico.';
            }
        }

        $name = text_lower(trim($name));
        if (text_length($name) >= 4 && str_contains($lower, $name)) {
            return 'La contraseña no puede contener tu nombre.';
        }

        return null;
    }

    public static function assertLoginAllowed(string $email, string $ip): void
    {
        $since = gmdate('Y-m-d\TH:i:s\Z', time() - self::LOGIN_WINDOW_SECONDS);
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')
            ->execute([gmdate('Y-m-d\TH:i:s\Z', time() - 24 * 60 * 60)]);

        $byAccount = $pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE email = ?' . sql_nocase() . ' AND ip = ? AND attempted_at >= ?'
        );
        $byAccount->execute([$email, $ip, $since]);
        $accountCount = (int) $byAccount->fetchColumn();

        $byAccountGlobal = $pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE email = ?' . sql_nocase() . ' AND attempted_at >= ?'
        );
        $byAccountGlobal->execute([$email, $since]);
        $accountGlobalCount = (int) $byAccountGlobal->fetchColumn();

        $byIp = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at >= ?');
        $byIp->execute([$ip, $since]);
        $ipCount = (int) $byIp->fetchColumn();

        if ($accountGlobalCount >= self::LOGIN_MAX_PER_ACCOUNT_GLOBAL
            || $accountCount >= self::LOGIN_MAX_PER_ACCOUNT
            || $ipCount >= self::LOGIN_MAX_PER_IP) {
            header('Retry-After: ' . self::LOGIN_WINDOW_SECONDS);
            throw new HttpError(
                'Demasiados intentos de acceso. Espera unos minutos antes de volver a intentarlo.',
                429
            );
        }
    }

    public static function recordLoginFailure(string $email, string $ip): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO login_attempts (email, ip, attempted_at) VALUES (?, ?, ?)')
            ->execute([text_lower($email), $ip, now_utc()]);
    }

    public static function clearLoginFailures(string $email, string $ip): void
    {
        Database::pdo()
            ->prepare('DELETE FROM login_attempts WHERE email = ?' . sql_nocase() . ' AND ip = ?')
            ->execute([$email, $ip]);
    }

    /**
     * Evita que una celda CSV se interprete como fórmula en Excel o Sheets.
     */
    public static function csvCell(mixed $value): string
    {
        $text = (string) $value;
        if (preg_match('/^[\s]*[=+\-@\t\r]/', $text) === 1) {
            return "'" . $text;
        }
        return $text;
    }

    public static function headerFilename(string $value): string
    {
        $value = str_replace(["\r", "\n", "\0", '"'], '', $value);
        return trim($value) !== '' ? trim($value) : 'adjunto';
    }
}
