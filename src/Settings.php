<?php
declare(strict_types=1);

final class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $rows = Database::pdo()->query('SELECT `key`, `value` FROM settings')->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['key']] = $row['value'];
        }
        self::$cache = $settings;
        return $settings;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function set(string $key, ?string $value): void
    {
        if (Database::driver() === 'mysql') {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO settings (`key`, `value`, updated_at) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = VALUES(updated_at)'
            );
        } else {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO settings (`key`, `value`, updated_at) VALUES (?, ?, ?)
                 ON CONFLICT(`key`) DO UPDATE SET `value` = excluded.`value`, updated_at = excluded.updated_at'
            );
        }
        $stmt->execute([$key, $value, now_utc()]);
        self::$cache = null;
    }

    public static function setMany(array $values): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($values as $key => $value) {
                self::set($key, $value === null ? null : (string) $value);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        self::$cache = null;
    }

    public static function encryptSecret(?string $plain): string
    {
        if ($plain === null || $plain === '') {
            return '';
        }
        $key = hash('sha256', (string) config('app_key'), true);
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('No se pudo cifrar la contraseña de correo.');
        }
        return base64_encode($iv . $tag . $cipher);
    }

    public static function decryptSecret(?string $stored): string
    {
        if ($stored === null || $stored === '') {
            return '';
        }
        $raw = base64_decode($stored, true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }
        $key = hash('sha256', (string) config('app_key'), true);
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? '' : $plain;
    }
}
