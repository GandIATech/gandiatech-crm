<?php
declare(strict_types=1);

const FEEDBACK_TYPES = [
    'error' => 'Error',
    'mejora' => 'Sugerencia',
    'pregunta' => 'Duda',
];

/**
 * Envía los comentarios de los usuarios al buzón configurado.
 *
 * Usa el mismo SMTP que las notificaciones del CRM. Si el correo no está
 * configurado, se devuelve un aviso claro en lugar de perder el comentario en
 * silencio (el intento queda en email_log).
 */
function handle_feedback(string $method, array $user): void
{
    if ($method !== 'POST') {
        throw new HttpError('Método no permitido.', 405);
    }

    $input = json_input();
    $type = clean_string($input['type'] ?? 'error', 20);
    if (!isset(FEEDBACK_TYPES[$type])) {
        throw new HttpError('Revisa los campos marcados.', 422, ['type' => 'El tipo de comentario no es válido.']);
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

    $recipient = trim((string) config('feedback_email', 'contacto@gandia.tech'));
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        throw new HttpError('El buzón de comentarios no está configurado correctamente.', 503);
    }

    $last = (int) ($_SESSION['feedback_last_at'] ?? 0);
    if ($last > 0 && time() - $last < 60) {
        throw new HttpError('Espera un minuto antes de enviar otro comentario.', 429);
    }

    $userName = (string) ($user['name'] ?? 'Usuario');
    $userEmail = (string) ($user['email'] ?? '');
    $role = (string) ($user['role'] ?? '');

    $lines = [
        'Tipo: ' . FEEDBACK_TYPES[$type],
        'De: ' . $userName . ($userEmail !== '' ? ' <' . $userEmail . '>' : ''),
        'Rol: ' . $role,
    ];
    if ($page !== '') {
        $lines[] = 'Página: ' . $page;
    }
    if ($includeContext) {
        $lines[] = 'Versión: ' . APP_VERSION . ' · PHP ' . PHP_VERSION . ' · ' . Database::driver();
    }
    $lines[] = '';
    $lines[] = $description;
    $text = implode("\n", $lines);

    $subject = '[CRM] ' . FEEDBACK_TYPES[$type] . ': ' . $title;
    $html = Mailer::template($title, $text);
    $result = Mailer::deliver($recipient, 'GandiaTech', $subject, $html, $text, $userEmail !== '' ? $userEmail : null);

    if (!$result['ok']) {
        error_log('[CRM] Comentario no enviado: ' . $result['error']);
        if ($result['status'] === 'omitido') {
            throw new HttpError('El envío de comentarios no está configurado. Avisa al administrador.', 503);
        }
        throw new HttpError('No se pudo enviar el comentario. Inténtalo más tarde.', 502);
    }

    $_SESSION['feedback_last_at'] = time();

    json_response(['message' => 'Comentario enviado.']);
}
