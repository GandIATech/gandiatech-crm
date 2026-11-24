<?php
declare(strict_types=1);

function lead_status_or_fail(?int $statusId): ?int
{
    if ($statusId === null) {
        $first = Database::pdo()->query('SELECT id FROM lead_statuses ORDER BY position, id LIMIT 1')->fetchColumn();
        return $first ? (int) $first : null;
    }
    $stmt = Database::pdo()->prepare('SELECT id FROM lead_statuses WHERE id = ?');
    $stmt->execute([$statusId]);
    if (!$stmt->fetch()) {
        throw new HttpError('Revisa los campos marcados.', 422, ['status_id' => 'El estado seleccionado no existe.']);
    }
    return $statusId;
}

const LEAD_LOST_REASONS = ['Precio elevado', 'Eligió competidor', 'Sin respuesta', 'Proyecto cancelado', 'Otro'];
const LEAD_INTERACTION_TYPES = ['llamada', 'reunion', 'correo', 'nota'];

function handle_leads(string $method, ?int $id, ?string $action, array $user): void
{
    $pdo = Database::pdo();

    if ($id !== null && $action === 'activities' && $method === 'POST') {
        $lead = require_owned_access(record_or_404('leads', $id, 'Lead no encontrado.'), $user, 'Lead no encontrado.');
        $input = json_input();
        $type = clean_string($input['type'] ?? '', 20);
        if (!in_array($type, LEAD_INTERACTION_TYPES, true)) {
            throw new HttpError('Revisa los campos marcados.', 422, ['type' => 'El tipo de interacción no es válido.']);
        }
        $description = require_text($input, 'description', 'La descripción', 2000);
        $dateRaw = clean_string($input['date'] ?? '', 10);
        $date = valid_date($dateRaw !== '' ? $dateRaw : today());
        if ($date === null) {
            throw new HttpError('Revisa los campos marcados.', 422, ['date' => 'La fecha no es válida.']);
        }
        $labels = ['llamada' => 'Llamada', 'reunion' => 'Reunión', 'correo' => 'Correo', 'nota' => 'Nota comercial'];
        $pdo->prepare(
            'INSERT INTO activities (user_id, entity_type, entity_id, action, description, interaction_type, activity_date, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            (int) $user['id'],
            'lead',
            $id,
            'interaccion',
            $description,
            $type,
            $date,
            now_utc(),
        ]);
        json_response([
            'id' => (int) $pdo->lastInsertId(),
            'type' => $type,
            'type_label' => $labels[$type],
            'description' => $description,
            'date' => $date,
        ], 201);
    }

    if ($id === null && $method === 'GET') {
        [$page, $perPage, $offset] = pagination();
        [$where, $params] = leads_filters($user);

        $countStmt = $pdo->prepare(
            "SELECT COUNT(*), COALESCE(SUM(l.value), 0) FROM leads l LEFT JOIN contacts c ON c.id = l.contact_id WHERE $where"
        );
        $countStmt->execute($params);
        [$total, $totalValue] = $countStmt->fetch(PDO::FETCH_NUM);

        $sql = "SELECT l.*, c.name AS contact_name, c.email AS contact_email, c.phone AS contact_phone,
                       c.company AS contact_company, s.name AS status_name, s.color AS status_color,
                       s.is_won, s.is_lost, u.name AS owner_name, u.avatar_color AS owner_color,
                       (SELECT COUNT(*) FROM tasks t WHERE t.lead_id = l.id AND t.status <> 'completada' AND t.deleted_at IS NULL) AS open_tasks
                FROM leads l
                LEFT JOIN contacts c ON c.id = l.contact_id
                LEFT JOIN lead_statuses s ON s.id = l.status_id
                LEFT JOIN users u ON u.id = l.owner_id
                WHERE $where
                ORDER BY l.updated_at DESC, l.id DESC
                LIMIT ? OFFSET ?";
        $stmt = $pdo->prepare($sql);
        $position = 1;
        foreach ($params as $param) {
            $stmt->bindValue($position++, $param);
        }
        $stmt->bindValue($position++, $perPage, PDO::PARAM_INT);
        $stmt->bindValue($position, $offset, PDO::PARAM_INT);
        $stmt->execute();

        json_response($stmt->fetchAll(), 200, pagination_meta((int) $total, $page, $perPage) + [
            'total_value' => (float) $totalValue,
        ]);
    }

    if ($id !== null && $method === 'GET') {
        $lead = lead_full($id, $user);
        $tasksStmt = $pdo->prepare(
            "SELECT t.*, u.name AS assigned_name, u.avatar_color AS assigned_color
             FROM tasks t LEFT JOIN users u ON u.id = t.assigned_to
             WHERE t.lead_id = ? AND t.deleted_at IS NULL
             ORDER BY CASE WHEN t.status = 'completada' THEN 1 ELSE 0 END, t.due_date IS NULL, t.due_date"
        );
        $tasksStmt->execute([$id]);

        $projectsStmt = $pdo->prepare(
            'SELECT id, name, status, budget FROM projects WHERE lead_id = ? AND deleted_at IS NULL ORDER BY created_at DESC'
        );
        $projectsStmt->execute([$id]);

        json_response([
            'lead' => $lead,
            'tasks' => $tasksStmt->fetchAll(),
            'projects' => $projectsStmt->fetchAll(),
            'activities' => activity_for('lead', $id, 50),
            'attachments' => attachments_for('lead', $id),
            'lost_reasons' => LEAD_LOST_REASONS,
        ]);
    }

    if ($id === null && $method === 'POST') {
        $input = json_input();
        $title = require_text($input, 'title', 'El título', 160);
        $statusId = lead_status_or_fail(int_or_null($input['status_id'] ?? null));
        $contactId = ensure_contact_id(int_or_null($input['contact_id'] ?? null), $user);
        $ownerId = user_can_view_all($user) && array_key_exists('owner_id', $input)
            ? ensure_user(int_or_null($input['owner_id']))
            : (int) $user['id'];
        if (array_key_exists('owner_id', $input) && $input['owner_id'] !== null && $input['owner_id'] !== '' && $ownerId === null) {
            throw new HttpError('Revisa los campos marcados.', 422, ['owner_id' => 'El responsable no existe.']);
        }
        $expected = valid_date(clean_string($input['expected_close_date'] ?? '', 10));
        if (clean_string($input['expected_close_date'] ?? '') !== '' && $expected === null) {
            throw new HttpError('Revisa los campos marcados.', 422, ['expected_close_date' => 'La fecha no es válida.']);
        }

        $pdo->prepare(
            'INSERT INTO leads (title, contact_id, company, value, status_id, source, owner_id, expected_close_date, notes, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $title,
            $contactId,
            clean_string($input['company'] ?? '', 160),
            money_value($input['value'] ?? 0),
            $statusId,
            clean_string($input['source'] ?? '', 80),
            $ownerId,
            $expected,
            clean_string($input['notes'] ?? '', 5000),
            now_utc(),
            now_utc(),
        ]);
        $newId = (int) $pdo->lastInsertId();

        log_activity('lead', $newId, 'lead_creado', 'ha creado el lead «' . $title . '»');

        $recipients = array_values(array_diff(Notifier::ownerAndAdmins($ownerId), [(int) $user['id']]));
        Notifier::send(
            $recipients,
            'lead',
            'Nuevo lead: ' . $title,
            $user['name'] . ' ha creado el lead «' . $title . '» y lo ha asignado a ' . ($ownerId ? user_name($ownerId) : 'nadie') . '.',
            crm_url('/app/leads/?lead=' . $newId)
        );

        json_response(lead_full($newId, $user), 201);
    }

    if ($id !== null && $method === 'PATCH') {
        $input = json_input();
        $current = require_owned_access(record_or_404('leads', $id, 'Lead no encontrado.'), $user, 'Lead no encontrado.');

        $title = array_key_exists('title', $input) ? require_text($input, 'title', 'El título', 160) : $current['title'];
        $statusId = array_key_exists('status_id', $input)
            ? lead_status_or_fail(int_or_null($input['status_id']))
            : int_or_null($current['status_id']);
        $contactId = array_key_exists('contact_id', $input)
            ? ensure_contact_id(int_or_null($input['contact_id']), $user)
            : int_or_null($current['contact_id']);
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

        $expectedInput = array_key_exists('expected_close_date', $input)
            ? clean_string($input['expected_close_date'], 10)
            : (string) ($current['expected_close_date'] ?? '');
        $expected = valid_date($expectedInput);
        if ($expectedInput !== '' && $expected === null) {
            throw new HttpError('Revisa los campos marcados.', 422, ['expected_close_date' => 'La fecha no es válida.']);
        }

        $newStatus = $statusId ? status_row($statusId) : null;
        $oldStatus = $current['status_id'] ? status_row((int) $current['status_id']) : null;
        $lostReason = array_key_exists('lost_reason', $input)
            ? clean_string($input['lost_reason'], 120)
            : (string) ($current['lost_reason'] ?? '');
        $isLost = $newStatus !== null && (int) ($newStatus['is_lost'] ?? 0) === 1;
        if ($isLost && trim($lostReason) === '') {
            throw new HttpError(
                'Indica el motivo de pérdida.',
                422,
                ['lost_reason' => 'Selecciona un motivo de pérdida para este estado.']
            );
        }
        if (!$isLost) {
            $lostReason = '';
        }

        $pdo->prepare(
            'UPDATE leads SET title = ?, contact_id = ?, company = ?, value = ?, status_id = ?, source = ?, owner_id = ?,
             expected_close_date = ?, notes = ?, lost_reason = ?, updated_at = ? WHERE id = ?'
        )->execute([
            $title,
            $contactId,
            array_key_exists('company', $input) ? clean_string($input['company'], 160) : $current['company'],
            array_key_exists('value', $input) ? money_value($input['value']) : $current['value'],
            $statusId,
            array_key_exists('source', $input) ? clean_string($input['source'], 80) : $current['source'],
            $ownerId,
            $expected,
            array_key_exists('notes', $input) ? clean_string($input['notes'], 5000) : $current['notes'],
            $lostReason !== '' ? $lostReason : null,
            now_utc(),
            $id,
        ]);

        log_activity('lead', $id, 'lead_actualizado', 'ha actualizado el lead «' . $title . '»');

        if ($statusId !== int_or_null($current['status_id']) && $newStatus) {
            log_activity(
                'lead',
                $id,
                'estado_cambiado',
                'ha cambiado el estado del lead «' . $title . '» a ' . $newStatus['name']
                    . ($isLost ? ' · Motivo: ' . $lostReason : '')
            );
            $recipients = array_values(array_diff(Notifier::ownerAndAdmins($ownerId), [(int) $user['id']]));
            Notifier::send(
                $recipients,
                'status',
                'Estado actualizado: ' . $title,
                $user['name'] . ' ha movido el lead «' . $title . '» de ' . ($oldStatus['name'] ?? 'sin estado')
                    . ' a ' . $newStatus['name'] . '.',
                crm_url('/app/leads/?lead=' . $id)
            );
        }

        if ($ownerId && $ownerId !== int_or_null($current['owner_id'])) {
            $recipients = array_values(array_diff([$ownerId], [(int) $user['id']]));
            Notifier::send(
                $recipients,
                'assignment',
                'Lead asignado: ' . $title,
                $user['name'] . ' te ha asignado el lead «' . $title . '».',
                crm_url('/app/leads/?lead=' . $id)
            );
        }

        json_response(lead_full($id, $user));
    }

    if ($id !== null && $method === 'DELETE') {
        $lead = require_owned_access(record_or_404('leads', $id, 'Lead no encontrado.'), $user, 'Lead no encontrado.');
        soft_delete('leads', $id);
        log_activity('lead', $id, 'lead_eliminado', 'ha movido el lead «' . $lead['title'] . '» a la papelera');
        json_response(['message' => 'Lead movido a la papelera.']);
    }

    throw new HttpError('Método no permitido.', 405);
}

function leads_filters(?array $user = null): array
{
    $where = ['l.deleted_at IS NULL'];
    $params = [];
    if ($user !== null) {
        $scope = ownership_where('l.owner_id', $user, $params);
        if ($scope !== null) {
            $where[] = $scope;
        }
    }

    $q = query_string('q');
    if ($q !== '') {
        $where[] = '(l.title LIKE ? OR l.company LIKE ? OR l.notes LIKE ? OR c.name LIKE ? OR c.email LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }

    $status = query_int('status_id', 0, 0, PHP_INT_MAX);
    if ($status > 0) {
        $where[] = 'l.status_id = ?';
        $params[] = $status;
    }

    $owner = query_int('owner_id', 0, 0, PHP_INT_MAX);
    if ($owner > 0) {
        $where[] = 'l.owner_id = ?';
        $params[] = $owner;
    }

    $source = query_string('source');
    if ($source !== '') {
        $where[] = 'l.source = ?';
        $params[] = $source;
    }

    return [implode(' AND ', $where), $params];
}

function status_row(int $id): ?array
{
    $stmt = Database::pdo()->prepare('SELECT id, name, color, is_won, is_lost FROM lead_statuses WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

