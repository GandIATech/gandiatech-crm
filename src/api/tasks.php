<?php
declare(strict_types=1);

const TASK_PRIORITIES = ['baja', 'media', 'alta'];
const TASK_STATUSES = ['pendiente', 'en_progreso', 'completada'];

function task_priority_or_fail(mixed $value): string
{
    $priority = clean_string($value, 20);
    if (!in_array($priority, TASK_PRIORITIES, true)) {
        throw new HttpError('Revisa los campos marcados.', 422, ['priority' => 'La prioridad no es válida.']);
    }
    return $priority;
}

function task_status_or_fail(mixed $value): string
{
    $status = clean_string($value, 20);
    if (!in_array($status, TASK_STATUSES, true)) {
        throw new HttpError('Revisa los campos marcados.', 422, ['status' => 'El estado de la tarea no es válido.']);
    }
    return $status;
}

function handle_tasks(string $method, ?int $id, array $user): void
{
    $pdo = Database::pdo();

    if ($id === null && $method === 'GET') {
        [$page, $perPage, $offset] = pagination();
        if (query_string('from') !== '' || query_string('to') !== '') {
            $perPage = query_int('per_page', 300, 1, 500);
            $offset = ($page - 1) * $perPage;
        }
        [$where, $params] = task_filters($user);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks t WHERE $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT t.*, u.name AS assigned_name, u.avatar_color AS assigned_color,
                    l.title AS lead_title, p.name AS project_name, c.name AS creator_name
             FROM tasks t
             LEFT JOIN users u ON u.id = t.assigned_to
             LEFT JOIN leads l ON l.id = t.lead_id
             LEFT JOIN projects p ON p.id = t.project_id
             LEFT JOIN users c ON c.id = t.created_by
             WHERE $where
             ORDER BY CASE WHEN t.status = 'completada' THEN 1 ELSE 0 END,
                      CASE WHEN t.due_date IS NULL THEN 1 ELSE 0 END,
                      t.due_date ASC,
                      CASE t.priority WHEN 'alta' THEN 0 WHEN 'media' THEN 1 ELSE 2 END,
                      t.id DESC
             LIMIT ? OFFSET ?"
        );
        $position = 1;
        foreach ($params as $param) {
            $stmt->bindValue($position++, $param);
        }
        $stmt->bindValue($position++, $perPage, PDO::PARAM_INT);
        $stmt->bindValue($position, $offset, PDO::PARAM_INT);
        $stmt->execute();

        json_response($stmt->fetchAll(), 200, pagination_meta($total, $page, $perPage));
    }

    if ($id !== null && $method === 'GET') {
        $task = task_full($id, $user);
        $stmt = $pdo->prepare(
            'SELECT te.*, u.name AS user_name, u.avatar_color
             FROM time_entries te LEFT JOIN users u ON u.id = te.user_id
             WHERE te.task_id = ? ORDER BY te.date DESC, te.id DESC'
        );
        $stmt->execute([$id]);
        json_response(array_merge($task, [
            'time_entries' => $stmt->fetchAll(),
            'attachments' => attachments_for('task', $id),
        ]));
    }

    if ($id === null && $method === 'POST') {
        $input = json_input();
        $title = require_text($input, 'title', 'El título', 180);
        $status = task_status_or_fail($input['status'] ?? 'pendiente');
        $priority = task_priority_or_fail($input['priority'] ?? 'media');
        $assignedTo = array_key_exists('assigned_to', $input)
            ? ensure_user(int_or_null($input['assigned_to']))
            : (int) $user['id'];
        if (array_key_exists('assigned_to', $input) && $input['assigned_to'] !== null && $input['assigned_to'] !== '' && !ensure_user(int_or_null($input['assigned_to']))) {
            throw new HttpError('Revisa los campos marcados.', 422, ['assigned_to' => 'El usuario asignado no existe.']);
        }
        $due = task_due_date($input['due_date'] ?? null);

        $pdo->prepare(
            'INSERT INTO tasks (title, description, due_date, priority, status, assigned_to, lead_id, project_id, created_by, completed_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $title,
            clean_string($input['description'] ?? '', 4000),
            $due,
            $priority,
            $status,
            $assignedTo,
            ensure_fk('leads', int_or_null($input['lead_id'] ?? null), $user),
            ensure_fk('projects', int_or_null($input['project_id'] ?? null), $user),
            (int) $user['id'],
            $status === 'completada' ? now_utc() : null,
            now_utc(),
            now_utc(),
        ]);
        $newId = (int) $pdo->lastInsertId();
        log_activity('task', $newId, 'tarea_creada', 'ha creado la tarea «' . $title . '»');

        if ($assignedTo && $assignedTo !== (int) $user['id']) {
            Notifier::send(
                [$assignedTo],
                'task',
                'Nueva tarea: ' . $title,
                $user['name'] . ' te ha asignado la tarea «' . $title . '»' . ($due ? ' para el ' . $due : '') . '.',
                '/app/tasks/?task=' . $newId
            );
        }

        json_response(task_full($newId, $user), 201);
    }

    if ($id !== null && $method === 'PATCH') {
        $input = json_input();
        $current = task_full($id, $user);
        $title = array_key_exists('title', $input) ? require_text($input, 'title', 'El título', 180) : $current['title'];
        $status = array_key_exists('status', $input) ? task_status_or_fail($input['status']) : $current['status'];
        $priority = array_key_exists('priority', $input) ? task_priority_or_fail($input['priority']) : $current['priority'];
        if (array_key_exists('assigned_to', $input) && $input['assigned_to'] !== null && $input['assigned_to'] !== '') {
            $assignedTo = ensure_user(int_or_null($input['assigned_to']));
            if ($assignedTo === null) {
                throw new HttpError('Revisa los campos marcados.', 422, ['assigned_to' => 'El usuario asignado no existe.']);
            }
        } else {
            $assignedTo = array_key_exists('assigned_to', $input) ? null : int_or_null($current['assigned_to']);
        }
        $due = array_key_exists('due_date', $input) ? task_due_date($input['due_date']) : $current['due_date'];

        $completedAt = $current['completed_at'];
        if ($status === 'completada' && $current['status'] !== 'completada') {
            $completedAt = now_utc();
        } elseif ($status !== 'completada') {
            $completedAt = null;
        }

        $pdo->prepare(
            'UPDATE tasks SET title = ?, description = ?, due_date = ?, priority = ?, status = ?, assigned_to = ?, lead_id = ?, project_id = ?, completed_at = ?, updated_at = ? WHERE id = ?'
        )->execute([
            $title,
            array_key_exists('description', $input) ? clean_string($input['description'], 4000) : $current['description'],
            $due,
            $priority,
            $status,
            $assignedTo,
            array_key_exists('lead_id', $input) ? ensure_fk('leads', int_or_null($input['lead_id']), $user) : int_or_null($current['lead_id']),
            array_key_exists('project_id', $input) ? ensure_fk('projects', int_or_null($input['project_id']), $user) : int_or_null($current['project_id']),
            $completedAt,
            now_utc(),
            $id,
        ]);

        log_activity('task', $id, 'tarea_actualizada', 'ha actualizado la tarea «' . $title . '»');

        if ($status === 'completada' && $current['status'] !== 'completada') {
            log_activity('task', $id, 'tarea_completada', 'ha completado la tarea «' . $title . '»');
            $recipients = [];
            if ($current['created_by']) {
                $recipients[] = (int) $current['created_by'];
            }
            $recipients = array_values(array_diff(array_unique($recipients), [(int) $user['id']]));
            Notifier::send(
                $recipients,
                'task',
                'Tarea completada: ' . $title,
                $user['name'] . ' ha completado la tarea «' . $title . '».',
                '/app/tasks/?task=' . $id
            );
        }

        if ($assignedTo && $assignedTo !== int_or_null($current['assigned_to'])) {
            Notifier::send(
                array_values(array_diff([$assignedTo], [(int) $user['id']])),
                'task',
                'Tarea asignada: ' . $title,
                $user['name'] . ' te ha asignado la tarea «' . $title . '».',
                '/app/tasks/?task=' . $id
            );
        }

        json_response(task_full($id, $user));
    }

    if ($id !== null && $method === 'DELETE') {
        $task = task_full($id, $user);
        soft_delete('tasks', $id);
        log_activity('task', $id, 'tarea_eliminada', 'ha movido la tarea «' . $task['title'] . '» a la papelera');
        json_response(['message' => 'Tarea movida a la papelera.']);
    }

    throw new HttpError('Método no permitido.', 405);
}

function task_due_date(mixed $value): ?string
{
    $raw = clean_string($value, 10);
    if ($raw === '') {
        return null;
    }
    $date = valid_date($raw);
    if ($date === null) {
        throw new HttpError('Revisa los campos marcados.', 422, ['due_date' => 'La fecha límite no es válida.']);
    }
    return $date;
}

function task_filters(?array $user = null): array
{
    $where = ['t.deleted_at IS NULL'];
    $params = [];
    if ($user !== null) {
        $scope = substr(task_visibility_scope('t', $user, $params), 5);
        if ($scope !== '') {
            $where[] = $scope;
        }
    }
    $q = query_string('q');
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = '(t.title LIKE ? OR t.description LIKE ?)';
        array_push($params, $like, $like);
    }

    $status = query_string('status');
    if (in_array($status, TASK_STATUSES, true)) {
        $where[] = 't.status = ?';
        $params[] = $status;
    } elseif (query_string('open') === '1') {
        $where[] = "t.status <> 'completada'";
    }

    $priority = query_string('priority');
    if (in_array($priority, TASK_PRIORITIES, true)) {
        $where[] = 't.priority = ?';
        $params[] = $priority;
    }

    $assigned = query_int('assigned_to', 0, 0, PHP_INT_MAX);
    if ($assigned > 0) {
        $where[] = 't.assigned_to = ?';
        $params[] = $assigned;
    }

    $lead = query_int('lead_id', 0, 0, PHP_INT_MAX);
    if ($lead > 0) {
        $where[] = 't.lead_id = ?';
        $params[] = $lead;
    }

    $project = query_int('project_id', 0, 0, PHP_INT_MAX);
    if ($project > 0) {
        $where[] = 't.project_id = ?';
        $params[] = $project;
    }

    if (query_string('overdue') === '1') {
        $where[] = "t.status <> 'completada' AND t.due_date IS NOT NULL AND t.due_date < ?";
        $params[] = today();
    }

    if (query_string('scope') === 'week') {
        $where[] = "t.status <> 'completada' AND t.due_date BETWEEN ? AND ?";
        $params[] = today();
        $params[] = date('Y-m-d', strtotime('+7 days'));
    }

    $from = valid_date(query_string('from'));
    if ($from !== null) {
        $where[] = 't.due_date >= ?';
        $params[] = $from;
    }
    $to = valid_date(query_string('to'));
    if ($to !== null) {
        $where[] = 't.due_date <= ?';
        $params[] = $to;
    }
    if (query_string('with_due') === '1') {
        $where[] = 't.due_date IS NOT NULL';
    }

    return [implode(' AND ', $where), $params];
}
