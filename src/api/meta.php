<?php
declare(strict_types=1);

function handle_meta(): never
{
    $pdo = Database::pdo();
    $user = Auth::requireUser();
    $viewAll = user_can_view_all($user);

    $users = $pdo->query(
        'SELECT id, name, email, role, is_director, avatar_color, is_active FROM users WHERE is_active = 1 ORDER BY name'
    )->fetchAll();

    $statuses = $pdo->query(
        'SELECT id, name, color, position, is_won, is_lost FROM lead_statuses ORDER BY position, id'
    )->fetchAll();

    $contactParams = [];
    $contactScope = $viewAll ? '' : ' AND owner_id = ?';
    if (!$viewAll) {
        $contactParams[] = (int) $user['id'];
    }
    $contactStmt = $pdo->prepare(
        'SELECT id, name, email, company FROM contacts WHERE deleted_at IS NULL' . $contactScope . ' ORDER BY name LIMIT 500'
    );
    $contactStmt->execute($contactParams);
    $contacts = $contactStmt->fetchAll();

    $leadParams = [];
    $leadScope = $viewAll ? '' : ' AND owner_id = ?';
    if (!$viewAll) {
        $leadParams[] = (int) $user['id'];
    }
    $leadStmt = $pdo->prepare(
        'SELECT id, title FROM leads WHERE deleted_at IS NULL' . $leadScope . ' ORDER BY created_at DESC LIMIT 300'
    );
    $leadStmt->execute($leadParams);
    $leads = $leadStmt->fetchAll();

    $projectParams = [];
    $projectScope = $viewAll ? '' : ' AND owner_id = ?';
    if (!$viewAll) {
        $projectParams[] = (int) $user['id'];
    }
    $projectStmt = $pdo->prepare(
        'SELECT id, name FROM projects WHERE deleted_at IS NULL' . $projectScope . ' ORDER BY created_at DESC LIMIT 300'
    );
    $projectStmt->execute($projectParams);
    $projects = $projectStmt->fetchAll();

    $templates = $pdo->query(
        'SELECT t.id, t.name, t.description, (SELECT COUNT(*) FROM task_template_items i WHERE i.template_id = t.id) AS items_count
         FROM task_templates t ORDER BY t.name'
    )->fetchAll();

    json_response([
        'users' => $users,
        'statuses' => $statuses,
        'contacts' => $contacts,
        'leads' => $leads,
        'projects' => $projects,
        'templates' => $templates,
        'lost_reasons' => defined('LEAD_LOST_REASONS') ? LEAD_LOST_REASONS : ['Precio elevado', 'Eligió competidor', 'Sin respuesta', 'Proyecto cancelado', 'Otro'],
    ]);
}
