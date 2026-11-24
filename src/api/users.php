<?php
declare(strict_types=1);

function handle_users(string $method, ?int $id, array $user): void
{
    $pdo = Database::pdo();
    $isAdmin = $user['role'] === 'admin';

    if ($id === null && $method === 'GET') {
        $includeInactive = $isAdmin && query_string('all') === '1';
        $sql = 'SELECT id, name, email, role, is_director, is_active, avatar_color, created_at, last_login_at FROM users';
        if (!$includeInactive) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY is_active DESC, name';
        json_response($pdo->query($sql)->fetchAll());
    }

    Auth::requireAdmin();

    if ($id === null && $method === 'POST') {
        $input = json_input();
        $name = require_text($input, 'name', 'El nombre', 120);
        $email = valid_email_or_fail($input);
        $password = string_value($input['password'] ?? '');
        [$role, $isDirector] = user_role_input($input['role'] ?? 'user');

        $policyError = Security::passwordPolicyError($password, $email, $name);
        if ($policyError !== null) {
            throw new HttpError('Revisa los campos marcados.', 422, ['password' => $policyError]);
        }
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?' . sql_nocase());
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new HttpError('Revisa los campos marcados.', 422, ['email' => 'Ese correo ya está en uso.']);
        }

        $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role, is_director, is_active, avatar_color, created_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?)'
        )->execute([
            $name,
            $email,
            Security::hashPassword($password),
            $role,
            $isDirector,
            normalize_color($input['avatar_color'] ?? null),
            now_utc(),
        ]);
        $newId = (int) $pdo->lastInsertId();
        log_activity('user', $newId, 'usuario_creado', 'ha creado el usuario «' . $name . '»');

        json_response(fetch_user_admin($newId), 201);
    }

    if ($id !== null && $method === 'PATCH') {
        $input = json_input();
        $current = record_or_404('users', $id, 'Usuario no encontrado.');

        $name = array_key_exists('name', $input) ? require_text($input, 'name', 'El nombre', 120) : $current['name'];
        $email = array_key_exists('email', $input) ? valid_email_or_fail($input) : $current['email'];
        [$role, $isDirector] = array_key_exists('role', $input)
            ? user_role_input($input['role'])
            : [$current['role'], (int) ($current['is_director'] ?? 0)];
        $active = array_key_exists('is_active', $input)
            ? (bounded_int($input['is_active'], 1, 0, 1) === 1 ? 1 : 0)
            : (int) $current['is_active'];

        if ((int) $user['id'] === $id && ($role !== 'admin' || $active !== 1)) {
            throw new HttpError('No puedes quitarte permisos de administrador ni desactivar tu propia cuenta.', 422);
        }

        if ($current['role'] === 'admin' && ($role !== 'admin' || $active !== 1)) {
            $admins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND id <> " . $id)->fetchColumn();
            if ($admins === 0) {
                throw new HttpError('No puedes dejar el CRM sin ningún administrador activo.', 422);
            }
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?' . sql_nocase() . ' AND id <> ?');
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) {
            throw new HttpError('Revisa los campos marcados.', 422, ['email' => 'Ese correo ya está en uso.']);
        }

        $fields = 'name = ?, email = ?, role = ?, is_director = ?, is_active = ?, avatar_color = ?';
        $values = [$name, $email, $role, $isDirector, $active, normalize_color($input['avatar_color'] ?? $current['avatar_color'], $current['avatar_color'])];

        $password = array_key_exists('password', $input) ? string_value($input['password']) : '';
        if ($password !== '') {
            $policyError = Security::passwordPolicyError($password, $email, $name);
            if ($policyError !== null) {
                throw new HttpError('Revisa los campos marcados.', 422, ['password' => $policyError]);
            }
            $fields .= ', password_hash = ?, password_changed_at = ?, must_change_password = ?';
            array_push(
                $values,
                Security::hashPassword($password),
                now_utc(),
                (int) $user['id'] === $id ? 0 : 1
            );
        }

        if ($password !== '' || $role !== $current['role'] || $active !== (int) $current['is_active']) {
            $fields .= ', session_version = session_version + 1';
        }

        $values[] = $id;
        $pdo->prepare("UPDATE users SET $fields WHERE id = ?")->execute($values);

        if ((int) $user['id'] === $id && ($password !== '' || $role !== $current['role'] || $active !== (int) $current['is_active'])) {
            $_SESSION['session_version'] = (int) ($current['session_version'] ?? 1) + 1;
            $_SESSION['last_activity'] = time();
        }
        log_activity('user', $id, 'usuario_actualizado', 'ha actualizado al usuario «' . $name . '»');

        json_response(fetch_user_admin($id));
    }

    if ($id !== null && $method === 'DELETE') {
        if ((int) $user['id'] === $id) {
            throw new HttpError('No puedes eliminar tu propia cuenta.', 422);
        }
        $target = record_or_404('users', $id, 'Usuario no encontrado.');
        if ($target['role'] === 'admin') {
            $admins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
            if ($admins <= 1) {
                throw new HttpError('No puedes eliminar al último administrador activo.', 422);
            }
        }
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        log_activity('user', $id, 'usuario_eliminado', 'ha eliminado al usuario «' . $target['name'] . '»');
        json_response(['message' => 'Usuario eliminado.']);
    }

    throw new HttpError('Método no permitido.', 405);
}

function user_role_input(mixed $value): array
{
    $role = clean_string($value, 20);
    if ($role === 'admin') {
        return ['admin', 0];
    }
    if ($role === 'director') {
        return ['user', 1];
    }
    return ['user', 0];
}

function fetch_user_admin(int $id): array
{
    $stmt = Database::pdo()->prepare(
        'SELECT id, name, email, role, is_director, is_active, avatar_color, created_at, last_login_at FROM users WHERE id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new HttpError('Usuario no encontrado.', 404);
    }
    return $row;
}
