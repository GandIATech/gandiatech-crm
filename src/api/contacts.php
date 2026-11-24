<?php
declare(strict_types=1);

function handle_contacts(string $method, ?int $id, array $user): void
{
    $pdo = Database::pdo();

    if ($id === null && $method === 'GET') {
        [$page, $perPage, $offset] = pagination();
        [$where, $params] = contact_filters($user);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM contacts c WHERE $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT c.*, u.name AS owner_name, u.avatar_color AS owner_color,
                    (SELECT COUNT(*) FROM leads l WHERE l.contact_id = c.id AND l.deleted_at IS NULL) AS leads_count
             FROM contacts c LEFT JOIN users u ON u.id = c.owner_id
             WHERE $where
             ORDER BY c.updated_at DESC, c.id DESC LIMIT ? OFFSET ?"
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
        $contact = contact_full($id, $user);
        $leadParams = [$id];
        $leadScope = ownership_scope('l.owner_id', $user, $leadParams);
        $stmt = $pdo->prepare(
            "SELECT l.id, l.title, l.value, l.updated_at, s.name AS status_name, s.color AS status_color
             FROM leads l LEFT JOIN lead_statuses s ON s.id = l.status_id
             WHERE l.contact_id = ? AND l.deleted_at IS NULL" . $leadScope . " ORDER BY l.updated_at DESC"
        );
        $stmt->execute($leadParams);
        json_response(['contact' => $contact, 'leads' => $stmt->fetchAll()]);
    }

    if ($id === null && $method === 'POST') {
        $input = json_input();
        $name = require_text($input, 'name', 'El nombre', 120);
        $email = valid_email_or_fail($input, 'email', false);
        $ownerId = user_can_view_all($user)
            ? (ensure_user(int_or_null($input['owner_id'] ?? null)) ?? (int) $user['id'])
            : (int) $user['id'];
        if (array_key_exists('owner_id', $input) && $input['owner_id'] !== null && $input['owner_id'] !== '' && !ensure_user(int_or_null($input['owner_id']))) {
            throw new HttpError('Revisa los campos marcados.', 422, ['owner_id' => 'El responsable no existe.']);
        }

        $pdo->prepare(
            'INSERT INTO contacts (name, email, phone, company, position, notes, owner_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $name,
            $email !== '' ? $email : null,
            clean_string($input['phone'] ?? '', 40),
            clean_string($input['company'] ?? '', 160),
            clean_string($input['position'] ?? '', 120),
            clean_string($input['notes'] ?? '', 3000),
            $ownerId,
            now_utc(),
            now_utc(),
        ]);
        $newId = (int) $pdo->lastInsertId();
        log_activity('contact', $newId, 'contacto_creado', 'ha creado el contacto «' . $name . '»');
        json_response(contact_full($newId, $user), 201);
    }

    if ($id !== null && $method === 'PATCH') {
        $input = json_input();
        $current = require_owned_access(record_or_404('contacts', $id, 'Contacto no encontrado.'), $user, 'Contacto no encontrado.');
        $name = array_key_exists('name', $input) ? require_text($input, 'name', 'El nombre', 120) : $current['name'];
        $email = array_key_exists('email', $input) ? valid_email_or_fail($input, 'email', false) : ($current['email'] ?? '');
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
            'UPDATE contacts SET name = ?, email = ?, phone = ?, company = ?, position = ?, notes = ?, owner_id = ?, updated_at = ? WHERE id = ?'
        )->execute([
            $name,
            $email !== '' ? $email : null,
            array_key_exists('phone', $input) ? clean_string($input['phone'], 40) : $current['phone'],
            array_key_exists('company', $input) ? clean_string($input['company'], 160) : $current['company'],
            array_key_exists('position', $input) ? clean_string($input['position'], 120) : $current['position'],
            array_key_exists('notes', $input) ? clean_string($input['notes'], 3000) : $current['notes'],
            $ownerId,
            now_utc(),
            $id,
        ]);
        log_activity('contact', $id, 'contacto_actualizado', 'ha actualizado el contacto «' . $name . '»');
        json_response(contact_full($id, $user));
    }

    if ($id !== null && $method === 'DELETE') {
        $contact = require_owned_access(record_or_404('contacts', $id, 'Contacto no encontrado.'), $user, 'Contacto no encontrado.');
        soft_delete('contacts', $id);
        log_activity('contact', $id, 'contacto_eliminado', 'ha movido el contacto «' . $contact['name'] . '» a la papelera');
        json_response(['message' => 'Contacto movido a la papelera.']);
    }

    throw new HttpError('Método no permitido.', 405);
}

function contact_filters(?array $user = null): array
{
    $where = ['c.deleted_at IS NULL'];
    $params = [];
    if ($user !== null) {
        $scope = ownership_where('c.owner_id', $user, $params);
        if ($scope !== null) {
            $where[] = $scope;
        }
    }
    $q = query_string('q');
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = '(c.name LIKE ? OR c.email LIKE ? OR c.company LIKE ? OR c.phone LIKE ? OR c.position LIKE ?)';
        array_push($params, $like, $like, $like, $like, $like);
    }
    $owner = query_int('owner_id', 0, 0, PHP_INT_MAX);
    if ($owner > 0) {
        $where[] = 'c.owner_id = ?';
        $params[] = $owner;
    }
    return [implode(' AND ', $where), $params];
}
