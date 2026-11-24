<?php
declare(strict_types=1);

function handle_time_entries(string $method, ?int $id, array $user): void
{
    $pdo = Database::pdo();
    $isAdmin = ($user['role'] ?? '') === 'admin';

    if ($id === null && $method === 'GET') {
        [$page, $perPage, $offset] = pagination();
        [$where, $params] = time_entry_filters($user);

        $countStmt = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(te.hours), 0) FROM time_entries te WHERE $where");
        $countStmt->execute($params);
        [$totalRows, $totalHours] = $countStmt->fetch(PDO::FETCH_NUM);

        $stmt = $pdo->prepare(
            "SELECT te.*, u.name AS user_name, u.avatar_color,
                    t.title AS task_title, p.name AS project_name
             FROM time_entries te
             LEFT JOIN users u ON u.id = te.user_id
             LEFT JOIN tasks t ON t.id = te.task_id
             LEFT JOIN projects p ON p.id = te.project_id
             WHERE $where
             ORDER BY te.date DESC, te.id DESC LIMIT ? OFFSET ?"
        );
        $position = 1;
        foreach ($params as $param) {
            $stmt->bindValue($position++, $param);
        }
        $stmt->bindValue($position++, $perPage, PDO::PARAM_INT);
        $stmt->bindValue($position, $offset, PDO::PARAM_INT);
        $stmt->execute();

        json_response($stmt->fetchAll(), 200, pagination_meta((int) $totalRows, $page, $perPage) + [
            'total_hours' => round((float) $totalHours, 2),
        ]);
    }

    if ($id === null && $method === 'POST') {
        $input = json_input();
        [$taskId, $projectId] = time_entry_targets($input, $user);
        $hours = time_entry_hours($input['hours'] ?? null);
        $date = time_entry_date($input['date'] ?? null);
        $userId = time_entry_user($input['user_id'] ?? null, $user, $isAdmin);

        $pdo->prepare(
            'INSERT INTO time_entries (task_id, project_id, user_id, hours, description, date, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $taskId,
            $projectId,
            $userId,
            $hours,
            clean_string($input['description'] ?? '', 1000),
            $date,
            now_utc(),
        ]);
        $newId = (int) $pdo->lastInsertId();
        time_entry_log_activity($newId, $userId, $projectId, $taskId, $hours, $date);

        json_response(time_entry_full($newId), 201);
    }

    if ($id !== null && $method === 'GET') {
        $entry = time_entry_full($id);
        if (!user_can_view_all($user) && (int) ($entry['user_id'] ?? 0) !== (int) $user['id']) {
            throw new HttpError('Registro de horas no encontrado.', 404);
        }
        json_response($entry);
    }

    if ($id !== null && $method === 'PATCH') {
        $current = record_or_404('time_entries', $id, 'Registro de horas no encontrado.');
        time_entry_guard($current, $user, $isAdmin);
        $input = json_input();

        [$taskId, $projectId] = time_entry_targets([
            'task_id' => array_key_exists('task_id', $input) ? $input['task_id'] : $current['task_id'],
            'project_id' => array_key_exists('project_id', $input) ? $input['project_id'] : $current['project_id'],
        ], $user);
        $hours = array_key_exists('hours', $input) ? time_entry_hours($input['hours']) : (float) $current['hours'];
        $date = array_key_exists('date', $input) ? time_entry_date($input['date']) : (string) $current['date'];
        $userId = array_key_exists('user_id', $input)
            ? time_entry_user($input['user_id'], $user, $isAdmin)
            : (int_or_null($current['user_id']) ?? (int) $user['id']);

        $pdo->prepare(
            'UPDATE time_entries SET task_id = ?, project_id = ?, user_id = ?, hours = ?, description = ?, date = ? WHERE id = ?'
        )->execute([
            $taskId,
            $projectId,
            $userId,
            $hours,
            array_key_exists('description', $input) ? clean_string($input['description'], 1000) : $current['description'],
            $date,
            $id,
        ]);
        log_activity('time', $id, 'horas_actualizadas', 'ha actualizado un registro de horas');

        json_response(time_entry_full($id));
    }

    if ($id !== null && $method === 'DELETE') {
        $current = record_or_404('time_entries', $id, 'Registro de horas no encontrado.');
        time_entry_guard($current, $user, $isAdmin);
        $pdo->prepare('DELETE FROM time_entries WHERE id = ?')->execute([$id]);
        log_activity('time', $id, 'horas_eliminadas', 'ha eliminado un registro de horas');
        json_response(['message' => 'Registro de horas eliminado.']);
    }

    throw new HttpError('Método no permitido.', 405);
}

function time_entry_filters(?array $user = null): array
{
    $where = ['1 = 1'];
    $params = [];
    if ($user !== null) {
        $scope = ownership_where('te.user_id', $user, $params);
        if ($scope !== null) {
            $where[] = $scope;
        }
    }

    $task = query_int('task_id', 0, 0, PHP_INT_MAX);
    if ($task > 0) {
        $where[] = 'te.task_id = ?';
        $params[] = $task;
    }
    $project = query_int('project_id', 0, 0, PHP_INT_MAX);
    if ($project > 0) {
        $where[] = 'te.project_id = ?';
        $params[] = $project;
    }
    $filterUserId = query_int('user_id', 0, 0, PHP_INT_MAX);
    if ($filterUserId > 0) {
        $where[] = 'te.user_id = ?';
        $params[] = $filterUserId;
    }
    $from = valid_date(query_string('from'));
    if ($from !== null) {
        $where[] = 'te.date >= ?';
        $params[] = $from;
    }
    $to = valid_date(query_string('to'));
    if ($to !== null) {
        $where[] = 'te.date <= ?';
        $params[] = $to;
    }

    return [implode(' AND ', $where), $params];
}

function time_entry_targets(array $input, ?array $user = null): array
{
    $taskId = int_or_null($input['task_id'] ?? null);
    $projectId = int_or_null($input['project_id'] ?? null);
    $projectExplicit = $projectId !== null;

    if ($taskId !== null) {
        $stmt = Database::pdo()->prepare('SELECT project_id FROM tasks WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();
        if (!$task) {
            throw new HttpError('Revisa los campos marcados.', 422, ['task_id' => 'La tarea no existe.']);
        }
        if ($user !== null) {
            require_entity_access('task', $taskId, $user);
        }
        $taskProject = int_or_null($task['project_id']);
        if ($projectId === null) {
            $projectId = $taskProject;
        } elseif ($taskProject !== null && $taskProject !== $projectId) {
            throw new HttpError('Revisa los campos marcados.', 422, ['project_id' => 'La tarea pertenece a otro proyecto.']);
        }
    }

    if ($projectId !== null && ($taskId === null || $projectExplicit)) {
        $stmt = Database::pdo()->prepare('SELECT id FROM projects WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$projectId]);
        if (!$stmt->fetch()) {
            throw new HttpError('Revisa los campos marcados.', 422, ['project_id' => 'El proyecto no existe.']);
        }
        if ($user !== null) {
            require_entity_access('project', $projectId, $user);
        }
    }

    if ($taskId === null && $projectId === null) {
        throw new HttpError('Revisa los campos marcados.', 422, ['task_id' => 'Indica una tarea o un proyecto.']);
    }

    return [$taskId, $projectId];
}

function time_entry_hours(mixed $value): float
{
    $hours = is_numeric($value) ? (float) $value : 0.0;
    if ($hours <= 0 || $hours > 24) {
        throw new HttpError('Revisa los campos marcados.', 422, ['hours' => 'Las horas deben estar entre 0,01 y 24.']);
    }
    return round($hours, 2);
}

function time_entry_date(mixed $value): string
{
    $raw = clean_string($value, 10);
    if ($raw === '') {
        return today();
    }
    $date = valid_date($raw);
    if ($date === null) {
        throw new HttpError('Revisa los campos marcados.', 422, ['date' => 'La fecha no es válida.']);
    }
    return $date;
}

function time_entry_user(mixed $value, array $user, bool $isAdmin): int
{
    $userId = int_or_null($value);
    if ($userId === null || $userId === (int) $user['id']) {
        return (int) $user['id'];
    }
    if (!$isAdmin) {
        throw new HttpError('Solo un administrador puede imputar horas a otra persona.', 403);
    }
    $checked = ensure_user($userId);
    if ($checked === null) {
        throw new HttpError('Revisa los campos marcados.', 422, ['user_id' => 'El usuario no existe.']);
    }
    return $checked;
}

function time_entry_guard(array $entry, array $user, bool $isAdmin): void
{
    if (!$isAdmin && (int) ($entry['user_id'] ?? 0) !== (int) $user['id']) {
        throw new HttpError('No tienes permisos para modificar este registro de horas.', 403);
    }
}

function time_entry_full(int $id): array
{
    $stmt = Database::pdo()->prepare(
        'SELECT te.*, u.name AS user_name, u.avatar_color, t.title AS task_title, p.name AS project_name
         FROM time_entries te
         LEFT JOIN users u ON u.id = te.user_id
         LEFT JOIN tasks t ON t.id = te.task_id
         LEFT JOIN projects p ON p.id = te.project_id
         WHERE te.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new HttpError('Registro de horas no encontrado.', 404);
    }
    return $row;
}

function time_entry_log_activity(int $id, int $userId, ?int $projectId, ?int $taskId, float $hours, string $date): void
{
    $description = 'ha imputado ' . rtrim(rtrim(number_format($hours, 2, ',', ''), '0'), ',') . ' h el ' . $date;
    log_activity('time', $id, 'horas_registradas', $description);
}
