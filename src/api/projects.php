<?php
declare(strict_types=1);

const PROJECT_STATUSES = ['planificado', 'en_progreso', 'en_pausa', 'completado', 'cancelado'];

function project_status_or_fail(mixed $status): string
{
    $value = clean_string($status, 30);
    if (!in_array($value, PROJECT_STATUSES, true)) {
        throw new HttpError('Revisa los campos marcados.', 422, ['status' => 'El estado del proyecto no es válido.']);
    }
    return $value;
}

function handle_projects(string $method, ?int $id, array $user): void
{
    $pdo = Database::pdo();

    if ($id === null && $method === 'GET') {
        [$page, $perPage, $offset] = pagination();
        [$where, $params] = project_filters($user);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM projects p LEFT JOIN contacts c ON c.id = p.contact_id WHERE $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT p.*, c.name AS contact_name, l.title AS lead_title, u.name AS owner_name, u.avatar_color AS owner_color,
                    (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.deleted_at IS NULL) AS tasks_total,
                    (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'completada' AND t.deleted_at IS NULL) AS tasks_done,
                    (SELECT COALESCE(SUM(te.hours), 0) FROM time_entries te WHERE te.project_id = p.id) AS hours_logged
             FROM projects p
             LEFT JOIN contacts c ON c.id = p.contact_id
             LEFT JOIN leads l ON l.id = p.lead_id
             LEFT JOIN users u ON u.id = p.owner_id
             WHERE $where
             ORDER BY p.updated_at DESC, p.id DESC LIMIT ? OFFSET ?"
        );
        $position = 1;
        foreach ($params as $param) {
            $stmt->bindValue($position++, $param);
        }
        $stmt->bindValue($position++, $perPage, PDO::PARAM_INT);
        $stmt->bindValue($position, $offset, PDO::PARAM_INT);
        $stmt->execute();

        json_response(array_map('project_with_progress', $stmt->fetchAll()), 200, pagination_meta($total, $page, $perPage));
    }

    if ($id !== null && $method === 'GET') {
        $project = project_full($id, $user);
        $tasksStmt = $pdo->prepare(
            "SELECT t.*, u.name AS assigned_name, u.avatar_color AS assigned_color
             FROM tasks t LEFT JOIN users u ON u.id = t.assigned_to
             WHERE t.project_id = ? AND t.deleted_at IS NULL
             ORDER BY CASE WHEN t.status = 'completada' THEN 1 ELSE 0 END, t.due_date IS NULL, t.due_date"
        );
        $tasksStmt->execute([$id]);
        json_response([
            'project' => $project,
            'tasks' => $tasksStmt->fetchAll(),
            'activities' => activity_for('project', $id, 50),
            'attachments' => attachments_for('project', $id),
            'time' => project_time_summary($project),
            'time_entries' => time_entries_for_project($id),
        ]);
    }

    if ($id === null && $method === 'POST') {
        $input = json_input();
        $name = require_text($input, 'name', 'El nombre', 160);
        $status = project_status_or_fail($input['status'] ?? 'planificado');
        [$start, $end] = project_dates($input);
        $ownerId = user_can_view_all($user)
            ? (ensure_user(int_or_null($input['owner_id'] ?? null)) ?? (int) $user['id'])
            : (int) $user['id'];
        if (array_key_exists('owner_id', $input) && $input['owner_id'] !== null && $input['owner_id'] !== '' && !ensure_user(int_or_null($input['owner_id']))) {
            throw new HttpError('Revisa los campos marcados.', 422, ['owner_id' => 'El responsable no existe.']);
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO projects (name, contact_id, lead_id, status, budget, budgeted_hours, hourly_rate, start_date, end_date, progress, owner_id, description, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $name,
                ensure_fk('contacts', int_or_null($input['contact_id'] ?? null), $user),
                ensure_fk('leads', int_or_null($input['lead_id'] ?? null), $user),
                $status,
                money_value($input['budget'] ?? 0),
                hours_value($input['budgeted_hours'] ?? 0),
                money_value($input['hourly_rate'] ?? 0),
                $start,
                $end,
                bounded_int($input['progress'] ?? 0, 0, 0, 100),
                $ownerId,
                clean_string($input['description'] ?? '', 5000),
                now_utc(),
                now_utc(),
            ]);
            $newId = (int) $pdo->lastInsertId();
            log_activity('project', $newId, 'proyecto_creado', 'ha creado el proyecto «' . $name . '»');

            $templateId = int_or_null($input['template_id'] ?? null);
            if ($templateId) {
                $created = apply_task_template($templateId, $newId, $ownerId, (int) $user['id'], $start);
                if ($created > 0) {
                    log_activity('project', $newId, 'plantilla_aplicada', 'ha aplicado una plantilla con ' . $created . ' tareas');
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $recipients = array_values(array_diff(Notifier::ownerAndAdmins($ownerId), [(int) $user['id']]));
        Notifier::send(
            $recipients,
            'project',
            'Nuevo proyecto: ' . $name,
            $user['name'] . ' ha creado el proyecto «' . $name . '» y lo ha asignado a ' . user_name($ownerId) . '.',
            crm_url('/app/projects/?project=' . $newId)
        );

        json_response(project_full($newId, $user), 201);
    }

    if ($id !== null && $method === 'PATCH') {
        $input = json_input();
        $current = require_owned_access(record_or_404('projects', $id, 'Proyecto no encontrado.'), $user, 'Proyecto no encontrado.');
        $name = array_key_exists('name', $input) ? require_text($input, 'name', 'El nombre', 160) : $current['name'];
        $status = array_key_exists('status', $input) ? project_status_or_fail($input['status']) : $current['status'];
        [$start, $end] = project_dates([
            'start_date' => array_key_exists('start_date', $input) ? $input['start_date'] : $current['start_date'],
            'end_date' => array_key_exists('end_date', $input) ? $input['end_date'] : $current['end_date'],
        ]);
        $progress = $current['progress'];
        if (array_key_exists('progress', $input)) {
            $tasksStmt = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE project_id = ? AND deleted_at IS NULL');
            $tasksStmt->execute([$id]);
            if ((int) $tasksStmt->fetchColumn() === 0) {
                $progress = bounded_int($input['progress'], (int) $current['progress'], 0, 100);
            }
        }
        $ownerId = int_or_null($current['owner_id']);
        if (user_can_view_all($user) && array_key_exists('owner_id', $input)) {
            if ($input['owner_id'] !== null && $input['owner_id'] !== '') {
                $ownerId = ensure_user(int_or_null($input['owner_id']));
                if ($ownerId === null) {
                    throw new HttpError('Revisa los campos marcados.', 422, ['owner_id' => 'El responsable no existe.']);
                }
            } else {
                $ownerId = null;
            }
        }

        $pdo->prepare(
            'UPDATE projects SET name = ?, contact_id = ?, lead_id = ?, status = ?, budget = ?, budgeted_hours = ?, hourly_rate = ?, start_date = ?, end_date = ?,
             progress = ?, owner_id = ?, description = ?, updated_at = ? WHERE id = ?'
        )->execute([
            $name,
            array_key_exists('contact_id', $input) ? ensure_fk('contacts', int_or_null($input['contact_id']), $user) : int_or_null($current['contact_id']),
            array_key_exists('lead_id', $input) ? ensure_fk('leads', int_or_null($input['lead_id']), $user) : int_or_null($current['lead_id']),
            $status,
            array_key_exists('budget', $input) ? money_value($input['budget']) : $current['budget'],
            array_key_exists('budgeted_hours', $input) ? hours_value($input['budgeted_hours']) : $current['budgeted_hours'],
            array_key_exists('hourly_rate', $input) ? money_value($input['hourly_rate']) : $current['hourly_rate'],
            $start,
            $end,
            $progress,
            $ownerId,
            array_key_exists('description', $input) ? clean_string($input['description'], 5000) : $current['description'],
            now_utc(),
            $id,
        ]);

        log_activity('project', $id, 'proyecto_actualizado', 'ha actualizado el proyecto «' . $name . '»');
        if ($status !== $current['status']) {
            log_activity('project', $id, 'proyecto_estado', 'ha cambiado el estado del proyecto «' . $name . '» a ' . $status);
        }

        if ($ownerId && $ownerId !== int_or_null($current['owner_id'])) {
            Notifier::send(
                array_values(array_diff([$ownerId], [(int) $user['id']])),
                'project',
                'Proyecto asignado: ' . $name,
                $user['name'] . ' te ha asignado el proyecto «' . $name . '».',
                crm_url('/app/projects/?project=' . $id)
            );
        }

        json_response(project_full($id, $user));
    }

    if ($id !== null && $method === 'DELETE') {
        $project = require_owned_access(record_or_404('projects', $id, 'Proyecto no encontrado.'), $user, 'Proyecto no encontrado.');
        soft_delete('projects', $id);
        log_activity('project', $id, 'proyecto_eliminado', 'ha movido el proyecto «' . $project['name'] . '» a la papelera');
        json_response(['message' => 'Proyecto movido a la papelera.']);
    }

    throw new HttpError('Método no permitido.', 405);
}

function project_dates(array $input): array
{
    $startRaw = clean_string($input['start_date'] ?? '', 10);
    $endRaw = clean_string($input['end_date'] ?? '', 10);
    $start = valid_date($startRaw);
    $end = valid_date($endRaw);
    $fields = [];
    if ($startRaw !== '' && $start === null) {
        $fields['start_date'] = 'La fecha de inicio no es válida.';
    }
    if ($endRaw !== '' && $end === null) {
        $fields['end_date'] = 'La fecha de fin no es válida.';
    }
    if ($fields !== []) {
        throw new HttpError('Revisa los campos marcados.', 422, $fields);
    }
    if ($start && $end && $end < $start) {
        throw new HttpError('Revisa los campos marcados.', 422, ['end_date' => 'La fecha de fin no puede ser anterior al inicio.']);
    }
    return [$start, $end];
}

function project_filters(?array $user = null): array
{
    $where = ['p.deleted_at IS NULL'];
    $params = [];
    if ($user !== null) {
        $scope = ownership_where('p.owner_id', $user, $params);
        if ($scope !== null) {
            $where[] = $scope;
        }
    }
    $q = query_string('q');
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = '(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)';
        array_push($params, $like, $like, $like);
    }
    $status = query_string('status');
    if (in_array($status, PROJECT_STATUSES, true)) {
        $where[] = 'p.status = ?';
        $params[] = $status;
    }
    $owner = query_int('owner_id', 0, 0, PHP_INT_MAX);
    if ($owner > 0) {
        $where[] = 'p.owner_id = ?';
        $params[] = $owner;
    }
    return [implode(' AND ', $where), $params];
}

function hours_value(mixed $value): float
{
    $hours = is_numeric($value) ? (float) $value : 0.0;
    return max(0.0, round($hours, 2));
}

function project_time_summary(array $project): array
{
    $budgeted = (float) ($project['budgeted_hours'] ?? 0);
    $logged = (float) ($project['hours_logged'] ?? 0);
    $rate = (float) ($project['hourly_rate'] ?? 0);
    $budget = (float) ($project['budget'] ?? 0);
    $laborCost = round($logged * $rate, 2);

    return [
        'budgeted_hours' => round($budgeted, 2),
        'hours_logged' => round($logged, 2),
        'hours_remaining' => round($budgeted - $logged, 2),
        'budgeted_hours_used' => $budgeted > 0 ? round(($logged / $budgeted) * 100, 1) : 0.0,
        'hourly_rate' => round($rate, 2),
        'budget' => round($budget, 2),
        'labor_cost' => $laborCost,
        'cost_deviation' => round($laborCost - $budget, 2),
        'budgeted_cost' => round($budgeted * $rate, 2),
    ];
}

function apply_task_template(int $templateId, int $projectId, ?int $ownerId, int $creatorId, ?string $startDate): int
{
    $pdo = Database::pdo();
    $stmt = $pdo->prepare('SELECT id, title, description, priority, offset_days FROM task_template_items WHERE template_id = ? ORDER BY position, id');
    $stmt->execute([$templateId]);
    $items = $stmt->fetchAll();
    if ($items === []) {
        throw new HttpError('La plantilla seleccionada no existe o no tiene tareas.', 422, ['template_id' => 'Plantilla no válida.']);
    }

    $base = $startDate !== null ? strtotime($startDate) : strtotime(today());
    if ($base === false) {
        $base = time();
    }
    $insert = $pdo->prepare(
        'INSERT INTO tasks (title, description, due_date, priority, status, assigned_to, project_id, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, \'pendiente\', ?, ?, ?, ?, ?)'
    );
    $created = 0;
    foreach ($items as $item) {
        $due = date('Y-m-d', strtotime('+' . max(0, (int) $item['offset_days']) . ' days', $base));
        $insert->execute([
            $item['title'],
            $item['description'],
            $due,
            $item['priority'],
            $ownerId,
            $projectId,
            $creatorId,
            now_utc(),
            now_utc(),
        ]);
        $taskId = (int) $pdo->lastInsertId();
        log_activity('task', $taskId, 'tarea_creada', 'tarea creada desde plantilla');
        $created++;
    }

    return $created;
}
