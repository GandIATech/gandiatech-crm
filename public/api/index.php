<?php
declare(strict_types=1);

$basePath = dirname(__DIR__);
if (!is_file($basePath . '/src/bootstrap.php')) {
    $basePath = dirname(__DIR__, 2);
}
require $basePath . '/src/bootstrap.php';

Security::headers();

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = preg_replace('#^/api#', '', $path);
$path = trim((string) $path, '/');
$segments = $path === '' ? [] : explode('/', $path);
$resource = $segments[0] ?? '';
$id = isset($segments[1]) && ctype_digit((string) $segments[1]) ? (int) $segments[1] : null;
$action = $id !== null ? ($segments[2] ?? null) : ($segments[1] ?? null);

try {
    if ($resource === 'health') {
        json_response(['status' => 'ok', 'time' => now_utc()]);
    }

    if (!app_installed()) {
        json_error('El CRM todavía no está instalado. Abre /install/ para completar la instalación.', 503);
    }

    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        Security::checkSameOrigin();
    }

    if ($resource === 'auth' && $action === 'login' && $method === 'POST') {
        require BASE_PATH . '/src/api/auth.php';
        handle_auth_login();
    }

    $user = Auth::requireUser();

    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        Auth::checkCsrf();
    }

    // Una contraseña inicial o temporal debe cambiarse antes de operar.
    if ((int) ($user['must_change_password'] ?? 0) === 1
        && $method !== 'GET'
        && !($resource === 'auth' && in_array($action, ['profile', 'password', 'logout'], true))) {
        throw new HttpError('Debes cambiar tu contraseña antes de continuar usando el CRM.', 403);
    }

    require BASE_PATH . '/src/api/_shared.php';

    if (in_array($resource, ['leads', 'contacts', 'projects', 'tasks', 'users', 'lead-statuses', 'time-entries', 'task-templates'], true)
        && $id === null && $action !== null) {
        throw new HttpError('Recurso no encontrado.', 404);
    }

    switch ($resource) {
        case 'auth':
            require BASE_PATH . '/src/api/auth.php';
            handle_auth($method, $action);
            break;
        case 'meta':
            require BASE_PATH . '/src/api/meta.php';
            handle_meta();
            break;
        case 'dashboard':
            require BASE_PATH . '/src/api/dashboard.php';
            handle_dashboard();
            break;
        case 'leads':
            require BASE_PATH . '/src/api/leads.php';
            handle_leads($method, $id, $action, $user);
            break;
        case 'contacts':
            require BASE_PATH . '/src/api/contacts.php';
            handle_contacts($method, $id, $user);
            break;
        case 'lead-statuses':
            require BASE_PATH . '/src/api/statuses.php';
            handle_statuses($method, $id, $user);
            break;
        case 'projects':
            require BASE_PATH . '/src/api/projects.php';
            handle_projects($method, $id, $user);
            break;
        case 'tasks':
            require BASE_PATH . '/src/api/tasks.php';
            handle_tasks($method, $id, $user);
            break;
        case 'users':
            require BASE_PATH . '/src/api/users.php';
            handle_users($method, $id, $user);
            break;
        case 'notifications':
            require BASE_PATH . '/src/api/notifications.php';
            handle_notifications($method, $id, $action);
            break;
        case 'settings':
            require BASE_PATH . '/src/api/settings.php';
            handle_settings($method, $segments, $user);
            break;
        case 'time-entries':
            require BASE_PATH . '/src/api/time_entries.php';
            handle_time_entries($method, $id, $user);
            break;
        case 'task-templates':
            require BASE_PATH . '/src/api/task_templates.php';
            handle_task_templates($method, $id, $user);
            break;
        case 'attachments':
            require BASE_PATH . '/src/api/attachments.php';
            handle_attachments($method, $id, $action, $user);
            break;
        case 'admin':
            require BASE_PATH . '/src/api/admin.php';
            handle_admin($method, $segments, $user);
            break;
        default:
            throw new HttpError('Recurso no encontrado.', 404);
    }
} catch (HttpError $e) {
    json_error($e->getMessage(), $e->status, $e->fields);
} catch (Throwable $e) {
    error_log('[CRM] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    $message = config('debug') ? $e->getMessage() : 'Ha ocurrido un error interno. Inténtalo de nuevo.';
    json_error($message, 500);
}
