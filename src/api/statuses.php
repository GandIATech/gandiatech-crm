<?php
declare(strict_types=1);

function handle_statuses(string $method, ?int $id, array $user): void
{
    $pdo = Database::pdo();

    if ($id === null && $method === 'GET') {
        $rows = $pdo->query(
            "SELECT s.*, (SELECT COUNT(*) FROM leads l WHERE l.status_id = s.id AND l.deleted_at IS NULL) AS leads_count
             FROM lead_statuses s ORDER BY s.position, s.id"
        )->fetchAll();
        json_response($rows);
    }

    Auth::requireAdmin();

    if ($id === null && $method === 'POST') {
        $input = json_input();
        $name = require_text($input, 'name', 'El nombre', 60);
        $color = normalize_color($input['color'] ?? null, '#FF5A36');
        $position = bounded_int($input['position'] ?? 0, 0, 0, 1000000);
        $isWon = (int) ($input['is_won'] ?? 0) === 1 ? 1 : 0;
        $isLost = (int) ($input['is_lost'] ?? 0) === 1 && !$isWon ? 1 : 0;

        $exists = $pdo->prepare('SELECT id FROM lead_statuses WHERE name = ?' . sql_nocase());
        $exists->execute([$name]);
        if ($exists->fetch()) {
            throw new HttpError('Revisa los campos marcados.', 422, ['name' => 'Ya existe un estado con ese nombre.']);
        }

        if ($isWon) {
            $pdo->exec('UPDATE lead_statuses SET is_won = 0');
        }
        if ($isLost) {
            $pdo->exec('UPDATE lead_statuses SET is_lost = 0');
        }

        $pdo->prepare(
            'INSERT INTO lead_statuses (name, color, position, is_won, is_lost, created_at) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$name, $color, $position, $isWon, $isLost, now_utc()]);
        $newId = (int) $pdo->lastInsertId();
        log_activity('status', $newId, 'estado_creado', 'ha creado el estado de lead «' . $name . '»');
        json_response(status_full($newId), 201);
    }

    if ($id !== null && $method === 'PATCH') {
        $input = json_input();
        $current = record_or_404('lead_statuses', $id, 'Estado no encontrado.');
        $name = array_key_exists('name', $input) ? require_text($input, 'name', 'El nombre', 60) : $current['name'];
        $color = array_key_exists('color', $input) ? normalize_color($input['color'], $current['color']) : $current['color'];
        $position = array_key_exists('position', $input) ? bounded_int($input['position'], 0, 0, 1000000) : (int) $current['position'];
        $isWon = array_key_exists('is_won', $input) ? ((int) $input['is_won'] === 1 ? 1 : 0) : (int) $current['is_won'];
        $isLost = array_key_exists('is_lost', $input) ? ((int) $input['is_lost'] === 1 ? 1 : 0) : (int) $current['is_lost'];
        if ($isWon) {
            $isLost = 0;
        }

        $exists = $pdo->prepare('SELECT id FROM lead_statuses WHERE name = ?' . sql_nocase() . ' AND id <> ?');
        $exists->execute([$name, $id]);
        if ($exists->fetch()) {
            throw new HttpError('Revisa los campos marcados.', 422, ['name' => 'Ya existe un estado con ese nombre.']);
        }

        if ($isWon) {
            $pdo->prepare('UPDATE lead_statuses SET is_won = 0 WHERE id <> ?')->execute([$id]);
        }
        if ($isLost) {
            $pdo->prepare('UPDATE lead_statuses SET is_lost = 0 WHERE id <> ?')->execute([$id]);
        }

        $pdo->prepare(
            'UPDATE lead_statuses SET name = ?, color = ?, position = ?, is_won = ?, is_lost = ? WHERE id = ?'
        )->execute([$name, $color, $position, $isWon, $isLost, $id]);
        log_activity('status', $id, 'estado_actualizado', 'ha actualizado el estado de lead «' . $name . '»');
        json_response(status_full($id));
    }

    if ($id !== null && $method === 'DELETE') {
        $status = record_or_404('lead_statuses', $id, 'Estado no encontrado.');
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM leads WHERE status_id = ?');
        $countStmt->execute([$id]);
        if ((int) $countStmt->fetchColumn() > 0) {
            throw new HttpError('No puedes eliminar un estado que tiene leads asignados. Reasígnalos primero.', 409);
        }
        $pdo->prepare('DELETE FROM lead_statuses WHERE id = ?')->execute([$id]);
        log_activity('status', $id, 'estado_eliminado', 'ha eliminado el estado de lead «' . $status['name'] . '»');
        json_response(['message' => 'Estado eliminado.']);
    }

    throw new HttpError('Método no permitido.', 405);
}

function status_full(int $id): array
{
    $stmt = Database::pdo()->prepare(
        'SELECT s.*, (SELECT COUNT(*) FROM leads l WHERE l.status_id = s.id AND l.deleted_at IS NULL) AS leads_count
         FROM lead_statuses s WHERE s.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new HttpError('Estado no encontrado.', 404);
    }
    return $row;
}
