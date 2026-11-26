<?php
declare(strict_types=1);

const FEEDBACK_TYPES = [
    'error' => 'Error',
    'mejora' => 'Mejora',
    'pregunta' => 'Pregunta',
];

/**
 * Crea una incidencia en el repositorio de GitHub configurado.
 *
 * Se usa la API REST con un token de servidor: el usuario del CRM no necesita
 * cuenta de GitHub y nunca ve el token.
 *
 * @return array{html_url: string, number: int}
 */
function github_create_issue(string $repo, string $token, string $api, array $payload): array
{
    $url = rtrim($api, '/') . '/repos/' . $repo . '/issues';
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($body === false) {
        throw new HttpError('No se pudo preparar el reporte.', 500);
    }

    $headers = [
        'Content-Type: application/json',
        'Accept: application/vnd.github+json',
        'Authorization: Bearer ' . $token,
        'User-Agent: GandiaTech-CRM/' . APP_VERSION,
        'X-GitHub-Api-Version: 2022-11-28',
    ];
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]);

    $http_response_header = [];
    $raw = @file_get_contents($url, false, $context);
    $status = 0;
    foreach ($http_response_header as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $match) === 1) {
            $status = (int) $match[1];
        }
    }

    $response = is_string($raw) ? json_decode($raw, true) : null;

    if ($status === 201 && is_array($response) && isset($response['html_url'])) {
        return [
            'html_url' => (string) $response['html_url'],
            'number' => (int) ($response['number'] ?? 0),
        ];
    }

    error_log('[CRM] GitHub respondió ' . $status . ' al crear una incidencia: ' . text_substr((string) $raw, 0, 500));

    if ($status === 401 || $status === 403) {
        throw new HttpError('GitHub rechazó la credencial del CRM. Revisa el token configurado.', 502);
    }
    if ($status === 404) {
        throw new HttpError('El repositorio de incidencias no existe o el token no tiene acceso.', 502);
    }
    if ($status === 422) {
        throw new HttpError('GitHub rechazó el reporte. Revisa el título y la descripción.', 422);
    }
    if ($status === 429) {
        throw new HttpError('GitHub ha limitado los envíos. Inténtalo más tarde.', 503);
    }
    throw new HttpError('No se pudo contactar con GitHub. Inténtalo más tarde.', 502);
}

function handle_feedback(string $method, array $user): void
{
    if ($method !== 'POST') {
        throw new HttpError('Método no permitido.', 405);
    }

    $input = json_input();
    $type = clean_string($input['type'] ?? 'error', 20);
    if (!isset(FEEDBACK_TYPES[$type])) {
        throw new HttpError('Revisa los campos marcados.', 422, ['type' => 'El tipo de reporte no es válido.']);
    }

    $title = require_text($input, 'title', 'El título', 140);
    if (text_length($title) < 5) {
        throw new HttpError('Revisa los campos marcados.', 422, ['title' => 'El título debe tener al menos 5 caracteres.']);
    }
    $description = require_text($input, 'description', 'La descripción', 5000);
    if (text_length($description) < 15) {
        throw new HttpError('Revisa los campos marcados.', 422, ['description' => 'Cuéntanos un poco más: al menos 15 caracteres.']);
    }

    $page = clean_string($input['page'] ?? '', 300);
    if ($page !== '' && !str_starts_with($page, '/')) {
        $page = '';
    }
    $includeContext = (bool) ($input['include_context'] ?? false);

    $settings = config('github', []);
    $settings = is_array($settings) ? $settings : [];
    $repo = trim((string) ($settings['repo'] ?? ''));
    $token = trim((string) ($settings['token'] ?? ''));
    $api = trim((string) ($settings['api'] ?? 'https://api.github.com'));
    if ($api === '') {
        $api = 'https://api.github.com';
    }

    if ($token === '' || preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo) !== 1) {
        throw new HttpError('El envío de incidencias no está configurado en este CRM. Avisa al administrador.', 503);
    }

    $last = (int) ($_SESSION['feedback_last_at'] ?? 0);
    if ($last > 0 && time() - $last < 60) {
        throw new HttpError('Espera un minuto antes de enviar otro reporte.', 429);
    }

    $body = $description . "\n\n---\n";
    if ($includeContext) {
        $rows = [
            'Versión' => APP_VERSION,
            'PHP' => PHP_VERSION,
            'Base de datos' => Database::driver(),
            'Rol' => (string) ($user['role'] ?? ''),
        ];
        if ($page !== '') {
            $rows['Página'] = str_replace('|', '\|', $page);
        }
        $body .= "\n**Contexto técnico**\n\n| Dato | Valor |\n|---|---|\n";
        foreach ($rows as $label => $value) {
            $body .= '| ' . $label . ' | ' . str_replace('|', '\|', $value) . " |\n";
        }
    }
    $body .= "\n_Enviado desde el CRM. No incluye datos de clientes._";

    $issue = github_create_issue($repo, $token, $api, [
        'title' => '[' . FEEDBACK_TYPES[$type] . '] ' . $title,
        'body' => $body,
    ]);

    $_SESSION['feedback_last_at'] = time();

    json_response([
        'url' => $issue['html_url'],
        'number' => $issue['number'],
    ], 201);
}
