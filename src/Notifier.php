<?php
declare(strict_types=1);

final class Notifier
{
    public static function send(array $userIds, string $type, string $title, string $message, string $link = '', bool $email = true): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), fn ($id) => $id > 0)));
        if ($ids === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare(
            "SELECT id, name, email FROM users WHERE is_active = 1 AND id IN ($placeholders)"
        );
        $stmt->execute($ids);
        $users = $stmt->fetchAll();
        if ($users === []) {
            return;
        }

        $insert = Database::pdo()->prepare(
            'INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($users as $user) {
            $insert->execute([$user['id'], $type, $title, $message, $link, now_utc()]);
        }

        if (!$email || (string) Settings::get('notifications_enabled', '1') !== '1') {
            return;
        }

        $html = Mailer::template($title, $message, $link !== '' ? 'Abrir en el CRM' : '', $link !== '' ? base_url() . $link : '');
        foreach ($users as $user) {
            if (!empty($user['email'])) {
                Mailer::send($user['email'], $user['name'], '[GandiaTech CRM] ' . $title, $html, $message);
            }
        }
    }

    public static function admins(): array
    {
        $rows = Database::pdo()->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1")->fetchAll();
        return array_map(fn ($row) => (int) $row['id'], $rows);
    }

    public static function ownerAndAdmins(?int $ownerId): array
    {
        $ids = self::admins();
        if ($ownerId) {
            $ids[] = $ownerId;
        }
        return array_values(array_unique($ids));
    }
}
