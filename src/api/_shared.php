<?php
declare(strict_types=1);

function record_or_404(string $table, int $id, string $message = 'Registro no encontrado.', bool $includeDeleted = false): array
{
    $allowed = ['leads', 'contacts', 'projects', 'tasks', 'users', 'lead_statuses', 'attachments', 'time_entries'];
    if (!in_array($table, $allowed, true)) {
        throw new RuntimeException('Tabla no permitida.');
    }
    $sql = "SELECT * FROM $table WHERE id = ?";
    if (!$includeDeleted && in_array($table, ['leads', 'contacts', 'projects', 'tasks'], true)) {
        $sql .= ' AND deleted_at IS NULL';
    }
    $stmt = Database::pdo()->prepare($sql);
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new HttpError($message, 404);
    }
    return $row;
}

function require_text(array $input, string $key, string $label, int $max = 255): string
{
    $raw = $input[$key] ?? '';
    if (!is_scalar($raw)) {
        throw new HttpError('Revisa los campos marcados.', 422, [$key => "$label no es válido."]);
    }
    $value = clean_string($raw, $max + 1);
    if (text_length($value) > $max) {
        throw new HttpError('Revisa los campos marcados.', 422, [$key => "$label no puede superar los $max caracteres."]);
    }
    if ($value === '') {
        throw new HttpError('Revisa los campos marcados.', 422, [$key => "$label es obligatorio."]);
    }
    return $value;
}

function valid_email_or_fail(array $input, string $key = 'email', bool $required = true): string
{
    $raw = $input[$key] ?? '';
    if (!is_scalar($raw)) {
        throw new HttpError('Revisa los campos marcados.', 422, [$key => 'Introduce un correo válido.']);
    }
    $email = trim((string) $raw);
    if ($email === '' && !$required) {
        return '';
    }
    if (text_length($email) > 190) {
        throw new HttpError('Revisa los campos marcados.', 422, [$key => 'El correo es demasiado largo.']);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new HttpError('Revisa los campos marcados.', 422, [$key => 'Introduce un correo válido.']);
    }
    return text_lower($email);
}

function normalize_color(mixed $value, string $fallback = '#5E6472'): string
{
    $color = is_string($value) ? trim($value) : '';
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtoupper($color) : $fallback;
}

function activity_for(string $type, int $id, int $limit = 25): array
{
    $stmt = Database::pdo()->prepare(
        'SELECT a.id, a.action, a.description, a.interaction_type, a.activity_date, a.created_at,
                u.name AS user_name, u.avatar_color
         FROM activities a LEFT JOIN users u ON u.id = a.user_id
         WHERE a.entity_type = ? AND a.entity_id = ?
         ORDER BY COALESCE(a.activity_date, substr(a.created_at, 1, 10)) DESC, a.created_at DESC, a.id DESC LIMIT ?'
    );
    $stmt->bindValue(1, $type);
    $stmt->bindValue(2, $id, PDO::PARAM_INT);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function lead_full(int $id, ?array $user = null): array
{
    $sql = "SELECT l.*, c.name AS contact_name, c.email AS contact_email, c.phone AS contact_phone,
                c.company AS contact_company, c.position AS contact_position,
                s.name AS status_name, s.color AS status_color, s.is_won, s.is_lost,
                u.name AS owner_name, u.avatar_color AS owner_color
         FROM leads l
         LEFT JOIN contacts c ON c.id = l.contact_id
         LEFT JOIN lead_statuses s ON s.id = l.status_id
         LEFT JOIN users u ON u.id = l.owner_id
         WHERE l.id = ? AND l.deleted_at IS NULL";
    $params = [$id];
    if ($user !== null) {
        $sql .= ownership_scope('l.owner_id', $user, $params);
    }
    $stmt = Database::pdo()->prepare($sql);
    $stmt->execute($params);
    $lead = $stmt->fetch();
    if (!$lead) {
        throw new HttpError('Lead no encontrado.', 404);
    }
    return $lead;
}

function project_progress(array $project): array
{
    $total = (int) ($project['tasks_total'] ?? 0);
    $done = (int) ($project['tasks_done'] ?? 0);
    if ($total > 0) {
        return [
            'progress' => (int) round($done / $total * 100),
            'progress_mode' => 'auto',
        ];
    }
    return [
        'progress' => max(0, min(100, (int) ($project['progress'] ?? 0))),
        'progress_mode' => 'manual',
    ];
}

function project_with_progress(array $project): array
{
    return array_merge($project, project_progress($project));
}

function project_full(int $id, ?array $user = null): array
{
    $sql = "SELECT p.*, c.name AS contact_name, c.company AS contact_company,
                l.title AS lead_title, u.name AS owner_name, u.avatar_color AS owner_color,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.deleted_at IS NULL) AS tasks_total,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'completada' AND t.deleted_at IS NULL) AS tasks_done,
                (SELECT COALESCE(SUM(te.hours), 0) FROM time_entries te WHERE te.project_id = p.id) AS hours_logged,
                (SELECT COALESCE(SUM(te.hours), 0) FROM time_entries te JOIN tasks tt ON tt.id = te.task_id WHERE tt.project_id = p.id) AS task_hours_logged
         FROM projects p
         LEFT JOIN contacts c ON c.id = p.contact_id
         LEFT JOIN leads l ON l.id = p.lead_id
         LEFT JOIN users u ON u.id = p.owner_id
         WHERE p.id = ? AND p.deleted_at IS NULL";
    $params = [$id];
    if ($user !== null) {
        $sql .= ownership_scope('p.owner_id', $user, $params);
    }
    $stmt = Database::pdo()->prepare($sql);
    $stmt->execute($params);
    $project = $stmt->fetch();
    if (!$project) {
        throw new HttpError('Proyecto no encontrado.', 404);
    }
    return project_with_progress($project);
}

function task_full(int $id, ?array $user = null): array
{
    $sql = "SELECT t.*, u.name AS assigned_name, u.avatar_color AS assigned_color,
                l.title AS lead_title, p.name AS project_name, c.name AS creator_name
         FROM tasks t
         LEFT JOIN users u ON u.id = t.assigned_to
         LEFT JOIN leads l ON l.id = t.lead_id
         LEFT JOIN projects p ON p.id = t.project_id
         LEFT JOIN users c ON c.id = t.created_by
         WHERE t.id = ? AND t.deleted_at IS NULL";
    $params = [$id];
    if ($user !== null) {
        $sql .= task_visibility_scope('t', $user, $params);
    }
    $stmt = Database::pdo()->prepare($sql);
    $stmt->execute($params);
    $task = $stmt->fetch();
    if (!$task) {
        throw new HttpError('Tarea no encontrada.', 404);
    }
    return $task;
}

function contact_full(int $id, ?array $user = null): array
{
    $sql = 'SELECT c.*, u.name AS owner_name,
                (SELECT COUNT(*) FROM leads l WHERE l.contact_id = c.id AND l.deleted_at IS NULL) AS leads_count
         FROM contacts c LEFT JOIN users u ON u.id = c.owner_id
         WHERE c.id = ? AND c.deleted_at IS NULL';
    $params = [$id];
    if ($user !== null) {
        $sql .= ownership_scope('c.owner_id', $user, $params);
    }
    $stmt = Database::pdo()->prepare($sql);
    $stmt->execute($params);
    $contact = $stmt->fetch();
    if (!$contact) {
        throw new HttpError('Contacto no encontrado.', 404);
    }
    return $contact;
}

function ensure_user(?int $userId): ?int
{
    if ($userId === null) {
        return null;
    }
    $stmt = Database::pdo()->prepare('SELECT id FROM users WHERE id = ? AND is_active = 1');
    $stmt->execute([$userId]);
    return $stmt->fetch() ? $userId : null;
}

function ensure_fk(string $table, ?int $id, ?array $user = null): ?int
{
    if ($id === null) {
        return null;
    }
    $allowed = ['contacts', 'leads', 'projects', 'users'];
    if (!in_array($table, $allowed, true)) {
        throw new RuntimeException('Relación no permitida.');
    }
    $softDeleted = in_array($table, ['contacts', 'leads', 'projects'], true);
    $sql = "SELECT id FROM $table WHERE id = ?";
    if ($softDeleted) {
        $sql .= ' AND deleted_at IS NULL';
    }
    $params = [$id];
    if ($user !== null && $softDeleted) {
        $sql .= ownership_scope('owner_id', $user, $params);
    }
    $stmt = Database::pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ? $id : null;
}

function ensure_contact_id(?int $contactId, ?array $user = null): ?int
{
    if ($contactId === null) {
        return null;
    }
    $sql = 'SELECT id FROM contacts WHERE id = ? AND deleted_at IS NULL';
    $params = [$contactId];
    if ($user !== null) {
        $sql .= ownership_scope('owner_id', $user, $params);
    }
    $stmt = Database::pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ? $contactId : null;
}

function user_name(int $id): string
{
    $stmt = Database::pdo()->prepare('SELECT name FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $name = $stmt->fetchColumn();
    return $name !== false ? (string) $name : 'Sin asignar';
}

function soft_delete(string $table, int $id): void
{
    $allowed = ['leads', 'contacts', 'projects', 'tasks'];
    if (!in_array($table, $allowed, true)) {
        throw new RuntimeException('Tabla no permitida.');
    }
    Database::pdo()->prepare("UPDATE $table SET deleted_at = ?, updated_at = ? WHERE id = ? AND deleted_at IS NULL")
        ->execute([now_utc(), now_utc(), $id]);
}

function entity_type_or_fail(mixed $value): string
{
    $type = clean_string($value, 20);
    if (!in_array($type, ['lead', 'project', 'task'], true)) {
        throw new HttpError('Tipo de entidad no válido.', 422);
    }
    return $type;
}

function entity_table(string $type): string
{
    return ['lead' => 'leads', 'project' => 'projects', 'task' => 'tasks'][$type] ?? 'leads';
}

function entity_exists(string $type, int $id): bool
{
    $table = entity_table($type);
    $stmt = Database::pdo()->prepare("SELECT id FROM $table WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    return (bool) $stmt->fetch();
}

function require_entity_access(string $type, int $id, array $user): void
{
    if (user_can_view_all($user)) {
        return;
    }
    if ($type === 'task') {
        task_full($id, $user);
        return;
    }
    $table = entity_table($type);
    $params = [$id];
    $sql = "SELECT id FROM $table WHERE id = ? AND deleted_at IS NULL";
    $sql .= ownership_scope('owner_id', $user, $params);
    $stmt = Database::pdo()->prepare($sql);
    $stmt->execute($params);
    if (!$stmt->fetch()) {
        throw new HttpError('Entidad no encontrada.', 404);
    }
}

function attachments_for(string $type, int $id): array
{
    $stmt = Database::pdo()->prepare(
        'SELECT a.id, a.entity_type, a.entity_id, a.original_name, a.mime_type, a.size, a.created_at,
                u.name AS uploaded_by_name
         FROM attachments a LEFT JOIN users u ON u.id = a.uploaded_by
         WHERE a.entity_type = ? AND a.entity_id = ?
         ORDER BY a.created_at DESC, a.id DESC'
    );
    $stmt->execute([$type, $id]);
    return $stmt->fetchAll();
}

function time_entries_for_project(int $projectId): array
{
    $stmt = Database::pdo()->prepare(
        'SELECT te.*, u.name AS user_name, u.avatar_color, t.title AS task_title
         FROM time_entries te
         LEFT JOIN users u ON u.id = te.user_id
         LEFT JOIN tasks t ON t.id = te.task_id
         WHERE te.project_id = ? ORDER BY te.date DESC, te.id DESC'
    );
    $stmt->execute([$projectId]);
    return $stmt->fetchAll();
}
