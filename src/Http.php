<?php
declare(strict_types=1);

final class HttpError extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 400,
        public readonly array $fields = []
    ) {
        parent::__construct($message);
    }
}

function json_response(mixed $data, int $status = 200, array $meta = []): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $payload = ['ok' => $status < 400, 'data' => $data];
    if ($meta !== []) {
        $payload['meta'] = $meta;
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function json_error(string $message, int $status = 400, array $fields = []): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'ok' => false,
        'error' => ['message' => $message, 'fields' => $fields],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function json_input(): array
{
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length > 1048576) {
        throw new HttpError('El cuerpo de la petición es demasiado grande.', 413);
    }

    $raw = file_get_contents('php://input', false, null, 0, 1048577);
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    if (strlen($raw) > 1048576) {
        throw new HttpError('El cuerpo de la petición es demasiado grande.', 413);
    }
    try {
        $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new HttpError('El cuerpo de la petición no es JSON válido.', 400);
    }
    if (!is_array($data)) {
        throw new HttpError('El cuerpo de la petición no es JSON válido.', 400);
    }
    return $data;
}

function query_string(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? text_substr(trim($value), 0, 200) : $default;
}

function query_int(string $key, int $default = 1, int $min = 1, int $max = 100): int
{
    $value = filter_var($_GET[$key] ?? $default, FILTER_VALIDATE_INT);
    if ($value === false) {
        return $default;
    }
    return max($min, min($max, (int) $value));
}

function pagination(): array
{
    $page = query_int('page', 1, 1, 100000);
    $perPage = query_int('per_page', 20, 1, 100);
    return [$page, $perPage, ($page - 1) * $perPage];
}

function pagination_meta(int $total, int $page, int $perPage): array
{
    return [
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
    ];
}
