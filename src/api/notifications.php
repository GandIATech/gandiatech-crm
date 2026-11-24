<?php
declare(strict_types=1);

function handle_notifications(string $method, ?int $id, ?string $action): void
{
    $user = Auth::requireUser();
    $pdo = Database::pdo();

    if ($method === 'GET' && $id === null) {
        $stmt = $pdo->prepare(
            'SELECT id, type, title, message, link, read_at, created_at
             FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 30'
        );
        $stmt->execute([$user['id']]);
        $items = $stmt->fetchAll();

        $unreadStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL');
        $unreadStmt->execute([$user['id']]);

        json_response($items, 200, ['unread' => (int) $unreadStmt->fetchColumn()]);
    }

    if ($method === 'POST' && $id === null && $action === 'read-all') {
        $pdo->prepare('UPDATE notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL')
            ->execute([now_utc(), $user['id']]);
        json_response(['message' => 'Notificaciones marcadas como leídas.']);
    }

    if ($method === 'POST' && $id !== null && $action === 'read') {
        $pdo->prepare('UPDATE notifications SET read_at = ? WHERE id = ? AND user_id = ? AND read_at IS NULL')
            ->execute([now_utc(), $id, $user['id']]);
        $unreadStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL');
        $unreadStmt->execute([$user['id']]);
        json_response(['unread' => (int) $unreadStmt->fetchColumn()]);
    }

    throw new HttpError('Acción no encontrada.', 404);
}
