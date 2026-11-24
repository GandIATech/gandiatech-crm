<?php
declare(strict_types=1);

function handle_auth_login(): never
{
    $input = json_input();
    $email = clean_string($input['email'] ?? '', 190);
    $password = string_value($input['password'] ?? '');

    $fields = [];
    if ($email === '') {
        $fields['email'] = 'Introduce tu correo.';
    }
    if ($password === '') {
        $fields['password'] = 'Introduce tu contraseña.';
    } elseif (strlen($password) > Security::PASSWORD_MAX_LENGTH) {
        $fields['password'] = 'La contraseña no es válida.';
    }
    if ($fields !== []) {
        throw new HttpError('Revisa los campos marcados.', 422, $fields);
    }

    $user = Auth::login($email, $password);
    json_response([
        'user' => $user,
        'csrf' => Auth::csrf(),
        'is_admin' => $user['role'] === 'admin',
        'is_director' => (int) ($user['is_director'] ?? 0),
        'must_change_password' => (int) ($user['must_change_password'] ?? 0),
        'can_view_all' => user_can_view_all($user),
        'app_name' => (string) Settings::get('app_name', 'GandiaTech CRM'),
    ]);
}

function handle_auth(string $method, ?string $action): void
{
    $user = Auth::requireUser();

    if ($action === 'me' && $method === 'GET') {
        json_response([
            'user' => Auth::publicUser($user),
            'csrf' => Auth::csrf(),
            'is_admin' => $user['role'] === 'admin',
            'is_director' => (int) ($user['is_director'] ?? 0),
            'must_change_password' => (int) ($user['must_change_password'] ?? 0),
            'can_view_all' => user_can_view_all($user),
            'app_name' => (string) Settings::get('app_name', 'GandiaTech CRM'),
        ]);
    }

    if ($action === 'logout' && $method === 'POST') {
        Auth::logout();
        json_response(null);
    }

    if ($action === 'profile' && $method === 'PATCH') {
        $input = json_input();
        $name = require_text($input, 'name', 'El nombre', 120);
        $email = valid_email_or_fail($input);
        $color = normalize_color($input['avatar_color'] ?? $user['avatar_color'], $user['avatar_color']);

        $stmt = Database::pdo()->prepare('SELECT id FROM users WHERE email = ?' . sql_nocase() . ' AND id <> ?');
        $stmt->execute([$email, $user['id']]);
        if ($stmt->fetch()) {
            throw new HttpError('Revisa los campos marcados.', 422, ['email' => 'Ese correo ya está en uso.']);
        }

        Database::pdo()->prepare('UPDATE users SET name = ?, email = ?, avatar_color = ? WHERE id = ?')
            ->execute([$name, $email, $color, $user['id']]);

        log_activity('user', (int) $user['id'], 'perfil_actualizado', 'ha actualizado su perfil');
        json_response(['user' => Auth::publicUser(record_or_404('users', (int) $user['id']))]);
    }

    if ($action === 'password' && $method === 'POST') {
        $input = json_input();
        $current = string_value($input['current_password'] ?? '');
        $new = string_value($input['new_password'] ?? '');

        $row = record_or_404('users', (int) $user['id']);
        if (!password_verify($current, $row['password_hash'])) {
            throw new HttpError('Revisa los campos marcados.', 422, ['current_password' => 'La contraseña actual no es correcta.']);
        }
        if (password_verify($new, $row['password_hash'])) {
            throw new HttpError('Revisa los campos marcados.', 422, ['new_password' => 'La nueva contraseña debe ser distinta de la actual.']);
        }
        $policyError = Security::passwordPolicyError($new, (string) $row['email'], (string) $row['name']);
        if ($policyError !== null) {
            throw new HttpError('Revisa los campos marcados.', 422, ['new_password' => $policyError]);
        }

        $version = (int) ($row['session_version'] ?? 1) + 1;
        Database::pdo()->prepare(
            'UPDATE users SET password_hash = ?, session_version = ?, must_change_password = 0, password_changed_at = ? WHERE id = ?'
        )->execute([Security::hashPassword($new), $version, now_utc(), $user['id']]);
        $_SESSION['session_version'] = $version;
        $_SESSION['last_activity'] = time();
        session_regenerate_id(true);
        log_activity('user', (int) $user['id'], 'password_actualizada', 'ha cambiado su contraseña');
        json_response([
            'message' => 'Contraseña actualizada correctamente.',
            'must_change_password' => 0,
        ]);
    }

    throw new HttpError('Acción no encontrada.', 404);
}
