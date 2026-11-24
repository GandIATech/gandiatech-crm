<?php
declare(strict_types=1);

function handle_dashboard(): never
{
    $pdo = Database::pdo();
    $user = Auth::requireUser();
    $viewAll = user_can_view_all($user);
    $ownerId = (int) $user['id'];

    $leadParams = $viewAll ? [] : [$ownerId];
    $leadScope = $viewAll ? '' : ' AND l.owner_id = ?';
    $projectParams = $viewAll ? [] : [$ownerId];
    $projectScope = $viewAll ? '' : ' AND p.owner_id = ?';

    $openFilter = 'COALESCE(s.is_won, 0) = 0 AND COALESCE(s.is_lost, 0) = 0';

    $openStmt = $pdo->prepare(
        "SELECT COUNT(*) AS count, COALESCE(SUM(l.value), 0) AS value
         FROM leads l LEFT JOIN lead_statuses s ON s.id = l.status_id
         WHERE $openFilter AND l.deleted_at IS NULL" . $leadScope
    );
    $openStmt->execute($leadParams);
    $open = $openStmt->fetch();

    $wonStmt = $pdo->prepare(
        "SELECT COUNT(*) AS count, COALESCE(SUM(l.value), 0) AS value
         FROM leads l LEFT JOIN lead_statuses s ON s.id = l.status_id
         WHERE COALESCE(s.is_won, 0) = 1 AND l.deleted_at IS NULL" . $leadScope
    );
    $wonStmt->execute($leadParams);
    $won = $wonStmt->fetch();

    $leadsTotalStmt = $pdo->prepare('SELECT COUNT(*) FROM leads l WHERE l.deleted_at IS NULL' . $leadScope);
    $leadsTotalStmt->execute($leadParams);
    $leadsTotal = (int) $leadsTotalStmt->fetchColumn();

    $projectsActiveStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM projects p WHERE p.status IN ('planificado', 'en_progreso', 'en_pausa') AND p.deleted_at IS NULL" . $projectScope
    );
    $projectsActiveStmt->execute($projectParams);
    $projectsActive = (int) $projectsActiveStmt->fetchColumn();

    $projectsTotalStmt = $pdo->prepare('SELECT COUNT(*) FROM projects p WHERE p.deleted_at IS NULL' . $projectScope);
    $projectsTotalStmt->execute($projectParams);
    $projectsTotal = (int) $projectsTotalStmt->fetchColumn();

    $taskParams = [];
    $taskScope = task_visibility_scope('t', $user, $taskParams);

    $tasksPendingStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM tasks t WHERE t.status <> 'completada' AND t.deleted_at IS NULL" . $taskScope
    );
    $tasksPendingStmt->execute($taskParams);
    $tasksPending = (int) $tasksPendingStmt->fetchColumn();

    $overdueStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM tasks t
         WHERE t.status <> 'completada' AND t.deleted_at IS NULL
           AND t.due_date IS NOT NULL AND t.due_date < ?" . $taskScope
    );
    $overdueStmt->execute([today(), ...$taskParams]);
    $tasksOverdue = (int) $overdueStmt->fetchColumn();

    $myTasks = $pdo->prepare(
        "SELECT COUNT(*) FROM tasks WHERE status <> 'completada' AND deleted_at IS NULL AND assigned_to = ?"
    );
    $myTasks->execute([$ownerId]);
    $myTasksCount = (int) $myTasks->fetchColumn();

    $pipelineStmt = $pdo->prepare(
        "SELECT s.id, s.name, s.color, s.is_won, s.is_lost,
                COUNT(l.id) AS count, COALESCE(SUM(l.value), 0) AS value
         FROM lead_statuses s
         LEFT JOIN leads l ON l.status_id = s.id AND l.deleted_at IS NULL" . $leadScope . "
         GROUP BY s.id, s.name, s.color, s.is_won, s.is_lost, s.position
         ORDER BY s.position, s.id"
    );
    $pipelineStmt->execute($leadParams);
    $pipeline = $pipelineStmt->fetchAll();

    $upcomingStmt = $pdo->prepare(
        "SELECT t.id, t.title, t.due_date, t.priority, t.status, t.assigned_to,
                u.name AS assigned_name, u.avatar_color AS assigned_color
         FROM tasks t LEFT JOIN users u ON u.id = t.assigned_to
         WHERE t.status <> 'completada' AND t.deleted_at IS NULL
           AND (t.due_date IS NULL OR t.due_date <= ?)" . $taskScope . "
         ORDER BY CASE WHEN t.due_date IS NULL THEN 1 ELSE 0 END, t.due_date, t.id
         LIMIT 6"
    );
    $upcomingStmt->execute([date('Y-m-d', strtotime('+14 days')), ...$taskParams]);
    $upcoming = $upcomingStmt->fetchAll();

    $recentLeadsStmt = $pdo->prepare(
        "SELECT l.id, l.title, l.value, l.updated_at, s.name AS status_name, s.color AS status_color,
                c.name AS contact_name, c.company AS contact_company, u.name AS owner_name
         FROM leads l
         LEFT JOIN lead_statuses s ON s.id = l.status_id
         LEFT JOIN contacts c ON c.id = l.contact_id
         LEFT JOIN users u ON u.id = l.owner_id
         WHERE l.deleted_at IS NULL" . $leadScope . "
         ORDER BY l.created_at DESC LIMIT 5"
    );
    $recentLeadsStmt->execute($leadParams);
    $recentLeads = $recentLeadsStmt->fetchAll();

    $activityParams = $viewAll ? [] : [$ownerId];
    $activityWhere = $viewAll ? '' : ' WHERE a.user_id = ?';
    $activityStmt = $pdo->prepare(
        "SELECT a.id, a.action, a.description, a.created_at, a.entity_type, a.entity_id,
                u.name AS user_name, u.avatar_color
         FROM activities a LEFT JOIN users u ON u.id = a.user_id" . $activityWhere . "
         ORDER BY a.created_at DESC, a.id DESC LIMIT 8"
    );
    $activityStmt->execute($activityParams);
    $activity = $activityStmt->fetchAll();

    json_response([
        'stats' => [
            'open_leads' => (int) $open['count'],
            'pipeline_value' => (float) $open['value'],
            'won_leads' => (int) $won['count'],
            'won_value' => (float) $won['value'],
            'leads_total' => $leadsTotal,
            'projects_active' => $projectsActive,
            'projects_total' => $projectsTotal,
            'tasks_pending' => $tasksPending,
            'tasks_overdue' => $tasksOverdue,
            'my_tasks' => $myTasksCount,
        ],
        'pipeline' => $pipeline,
        'upcoming_tasks' => $upcoming,
        'recent_leads' => $recentLeads,
        'activity' => $activity,
    ]);
}
