<?php
declare(strict_types=1);

function handle_settings(string $method, array $segments, array $user): void
{
    Auth::requireAdmin();
    $section = $segments[1] ?? '';
    $sub = $segments[2] ?? '';

    if ($section === 'backup' && $method === 'GET') {
        send_database_backup();
    }

    if ($section !== 'mail') {
        throw new HttpError('Ajuste no encontrado.', 404);
    }

    if ($method === 'GET' && $sub === '') {
        json_response(settings_mail_payload());
    }

    if ($method === 'PATCH' && $sub === '') {
        $input = json_input();
        $host = clean_string($input['mail_host'] ?? '', 190);
        if ($host !== '' && preg_match('/^[a-zA-Z0-9.\-:\[\]]{1,190}$/', $host) !== 1) {
            throw new HttpError('Revisa los campos marcados.', 422, ['mail_host' => 'El servidor SMTP no es válido.']);
        }
        $port = bounded_int($input['mail_port'] ?? 587, 587, 1, 65535);
        if ($port < 1 || $port > 65535) {
            throw new HttpError('Revisa los campos marcados.', 422, ['mail_port' => 'El puerto no es válido.']);
        }
        $encryption = clean_string($input['mail_encryption'] ?? 'tls', 10);
        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            throw new HttpError('Revisa los campos marcados.', 422, ['mail_encryption' => 'El cifrado no es válido.']);
        }
        $fromEmail = clean_string($input['mail_from_email'] ?? '', 190);
        if ($fromEmail !== '' && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            throw new HttpError('Revisa los campos marcados.', 422, ['mail_from_email' => 'Introduce un correo válido.']);
        }

        $values = [
            'app_name' => clean_string($input['app_name'] ?? 'GandiaTech CRM', 80) ?: 'GandiaTech CRM',
            'mail_host' => $host,
            'mail_port' => (string) $port,
            'mail_username' => clean_string($input['mail_username'] ?? '', 190),
            'mail_encryption' => $encryption,
            'mail_from_email' => $fromEmail,
            'mail_from_name' => clean_string($input['mail_from_name'] ?? 'GandiaTech CRM', 120) ?: 'GandiaTech CRM',
            'notifications_enabled' => !empty($input['notifications_enabled']) ? '1' : '0',
        ];

        if (!empty($input['clear_password'])) {
            $values['mail_password'] = '';
        } elseif (isset($input['mail_password'])) {
            $secret = string_value($input['mail_password']);
            if (strlen($secret) > 500) {
                throw new HttpError('Revisa los campos marcados.', 422, ['mail_password' => 'La contraseña SMTP es demasiado larga.']);
            }
            if ($secret !== '') {
                $values['mail_password'] = Settings::encryptSecret($secret);
            }
        }

        Settings::setMany($values);
        log_activity('settings', null, 'correo_actualizado', 'ha actualizado la configuración de correo');
        json_response(settings_mail_payload());
    }

    if ($method === 'POST' && $sub === 'test') {
        $input = json_input();
        $to = clean_string($input['to'] ?? $user['email'], 190);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new HttpError('Revisa los campos marcados.', 422, ['to' => 'Introduce un correo válido.']);
        }

        $html = Mailer::template(
            'Correo de prueba',
            'Si estás leyendo esto, la configuración SMTP del CRM funciona correctamente.',
            '',
            ''
        );
        $result = Mailer::deliver($to, $user['name'], '[GandiaTech CRM] Correo de prueba', $html, 'La configuración SMTP del CRM funciona correctamente.');
        if (!$result['ok']) {
            $reason = $result['status'] === 'omitido'
                ? 'Configura y guarda primero el servidor, el remitente y las credenciales SMTP.'
                : 'No se pudo enviar: ' . $result['error'];
            throw new HttpError($reason, 422);
        }

        json_response(['message' => 'Correo de prueba enviado a ' . $to . '.']);
    }

    throw new HttpError('Acción no encontrada.', 404);
}

function send_database_backup(): never
{
    if (Database::driver() === 'mysql') {
        send_mysql_backup();
    }

    $pdo = Database::pdo();
    $dir = BASE_PATH . '/data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $target = $dir . '/backup-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.sqlite';

    $lock = @fopen($dir . '/.crm-backup.lock', 'c');
    if ($lock !== false) {
        flock($lock, LOCK_SH);
    }
    try {
        $safe = str_replace("'", "''", $target);
        $pdo->exec("VACUUM INTO '" . $safe . "'");
    } finally {
        if ($lock !== false) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    if (!is_file($target)) {
        throw new HttpError('No se pudo generar la copia de seguridad.', 500);
    }

    $filename = 'crm-backup-' . date('Y-m-d-Hi') . '.sqlite';
    header('Content-Type: application/vnd.sqlite3');
    header('Content-Length: ' . (string) filesize($target));
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($target);
    @unlink($target);
    log_activity('settings', null, 'copia_seguridad', 'ha descargado una copia de seguridad de la base de datos');
    exit;
}

/**
 * Volcado SQL básico para instalaciones MySQL, donde VACUUM INTO no existe.
 * Incluye estructura y datos para poder restaurar con el cliente de MySQL.
 */
function send_mysql_backup(): never
{
    $pdo = Database::pdo();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $filename = 'crm-backup-' . date('Y-m-d-Hi') . '.sql';
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-Accel-Buffering: no');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        throw new HttpError('No se pudo generar la copia de seguridad.', 500);
    }

    // Lectura sin búfer para no cargar tablas enteras en memoria.
    if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
    }

    fwrite($out, "-- Copia de seguridad CRM\nSET FOREIGN_KEY_CHECKS=0;\n\n");
    foreach ($tables as $table) {
        $safeTable = str_replace('`', '``', (string) $table);
        $create = $pdo->query('SHOW CREATE TABLE `' . $safeTable . '`')->fetch(PDO::FETCH_NUM);
        if (!$create) {
            continue;
        }
        fwrite($out, "DROP TABLE IF EXISTS `" . $safeTable . "`;\n" . $create[1] . ";\n\n");

        $rows = $pdo->query('SELECT * FROM `' . $safeTable . '`');
        while ($rows !== false && ($row = $rows->fetch(PDO::FETCH_ASSOC)) !== false) {
            $columns = array_map(static fn ($column): string => '`' . str_replace('`', '``', (string) $column) . '`', array_keys($row));
            $values = array_map(static fn ($value): string => $value === null ? 'NULL' : $pdo->quote((string) $value), array_values($row));
            fwrite($out, 'INSERT INTO `' . $safeTable . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n");
        }
        fwrite($out, "\n");
        flush();
    }

    fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($out);
    log_activity('settings', null, 'copia_seguridad', 'ha descargado una copia de seguridad de la base de datos');
    exit;
}

function settings_mail_payload(): array
{
    $password = (string) Settings::get('mail_password', '');
    $logs = Database::pdo()->query(
        'SELECT id, recipient, subject, status, error, created_at FROM email_log ORDER BY created_at DESC, id DESC LIMIT 15'
    )->fetchAll();

    return [
        'mail' => [
            'app_name' => (string) Settings::get('app_name', 'GandiaTech CRM'),
            'host' => (string) Settings::get('mail_host', ''),
            'port' => (int) Settings::get('mail_port', '587'),
            'username' => (string) Settings::get('mail_username', ''),
            'has_password' => $password !== '',
            'encryption' => (string) Settings::get('mail_encryption', 'tls'),
            'from_email' => (string) Settings::get('mail_from_email', ''),
            'from_name' => (string) Settings::get('mail_from_name', ''),
            'notifications_enabled' => (string) Settings::get('notifications_enabled', '1') === '1',
        ],
        'logs' => $logs,
    ];
}
