<?php
declare(strict_types=1);

function handle_task_templates(string $method, ?int $id, array $user): void
{
    if ($method !== 'GET') {
        throw new HttpError('Método no permitido.', 405);
    }

    $pdo = Database::pdo();

    if ($id !== null) {
        $stmt = $pdo->prepare('SELECT * FROM task_templates WHERE id = ?');
        $stmt->execute([$id]);
        $template = $stmt->fetch();
        if (!$template) {
            throw new HttpError('Plantilla no encontrada.', 404);
        }
        $items = $pdo->prepare(
            'SELECT id, title, description, priority, offset_days, position
             FROM task_template_items WHERE template_id = ? ORDER BY position, id'
        );
        $items->execute([$id]);
        $template['items'] = $items->fetchAll();
        json_response($template);
    }

    $templates = $pdo->query(
        'SELECT t.*, (SELECT COUNT(*) FROM task_template_items i WHERE i.template_id = t.id) AS items_count
         FROM task_templates t ORDER BY t.name'
    )->fetchAll();
    json_response($templates);
}
