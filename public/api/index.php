<?php
declare(strict_types=1);

// Si el servidor usa un PHP antiguo, la API responde con un error explicativo
// en lugar de un 500 sin información.
$crmGuard = dirname(__DIR__) . '/src/php-guard.php';
if (!is_file($crmGuard)) {
    $crmGuard = dirname(__DIR__, 2) . '/src/php-guard.php';
}
require_once $crmGuard;
crm_php_version_guard('8.2', true);

$basePath = dirname(__DIR__);
if (!is_file($basePath . '/src/bootstrap.php')) {
    $basePath = dirname(__DIR__, 2);
}
require_once $basePath . '/src/bootstrap.php';

Security::headers();

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = crm_base_path();
if ($base !== '' && str_starts_with($path, $base . '/')) {
    $path = substr($path, strlen($base));
}
$path = preg_replace('#^/api#', '', $path);
$path = trim((string) $path, '/');
$segments = $path === '' ? [] : explode('/', $path);
$resource = $segments[0] ?? '';
$id = isset($segments[1]) && ctype_digit((string) $segments[1]) ? (int) $segments[1] : null;
$action = $id !== null ? ($segments[2] ?? null) : ($segments[1] ?? null);

try {
    if ($resource === 'health') {
        json_response(['status' => 'ok', 'version' => APP_VERSION, 'time' => now_utc()]);
    }

    if (!app_installed()) {
        json_error('El CRM todavía no está instalado. Abre ' . crm_url('/install/') . ' para completar la instalación.', 503);
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
        && $id === null && $action !== null && $action !== 'import') {
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
            if ($id === null && $action === 'import') {
                require BASE_PATH . '/src/api/import.php';
                handle_import($method, 'leads', $user);
            }
            require BASE_PATH . '/src/api/leads.php';
            handle_leads($method, $id, $action, $user);
            break;
        case 'contacts':
            if ($id === null && $action === 'import') {
                require BASE_PATH . '/src/api/import.php';
                handle_import($method, 'contacts', $user);
            }
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
        case 'updates':
            require BASE_PATH . '/src/api/updates.php';
            handle_updates($method, $action, $user);
            break;
        case 'feedback':
            require BASE_PATH . '/src/api/feedback.php';
            handle_feedback($method, $user);
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
