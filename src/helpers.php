<?php
declare(strict_types=1);

function now_utc(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}

function today(): string
{
    return date('Y-m-d');
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function string_value(mixed $value): string
{
    return is_string($value) ? $value : (is_int($value) || is_float($value) ? (string) $value : '');
}

function text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function text_substr(string $value, int $start, int $length): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, $start, $length, 'UTF-8');
    }
    // Sin mbstring, se corta por caracteres Unicode para no partir secuencias.
    if (preg_match_all('/./us', $value, $matches) !== false) {
        return implode('', array_slice($matches[0], $start, $length));
    }
    return substr($value, $start, $length);
}

function text_lower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function clean_string(mixed $value, int $max = 5000): string
{
    if (!is_scalar($value)) {
        return '';
    }
    $text = (string) $value;
    $validUtf8 = function_exists('mb_check_encoding')
        ? mb_check_encoding($text, 'UTF-8')
        : preg_match('//u', $text) === 1;
    if (!$validUtf8) {
        if (function_exists('mb_convert_encoding')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        } elseif (function_exists('iconv')) {
            $text = (string) iconv('UTF-8', 'UTF-8//IGNORE', $text);
        }
    }
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
    $text = trim($text);
    if (text_length($text) > $max) {
        $text = text_substr($text, 0, $max);
    }
    return $text;
}

function valid_date(?string $value): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }
    $date = DateTime::createFromFormat('!Y-m-d', trim($value));
    if (!$date || $date->format('Y-m-d') !== trim($value)) {
        return null;
    }
    $year = (int) $date->format('Y');
    if ($year < 1900 || $year > 2100) {
        return null;
    }
    return $date->format('Y-m-d');
}

function int_or_null(mixed $value): ?int
{
    if ($value === null || $value === '' || $value === false) {
        return null;
    }
    if (!is_numeric($value) || !is_finite((float) $value)) {
        return null;
    }
    $number = (int) $value;
    return $number > 0 ? $number : null;
}

function bounded_int(mixed $value, int $default = 0, int $min = 0, int $max = 1000000): int
{
    if (!is_numeric($value)) {
        return $default;
    }
    $number = (int) $value;
    return max($min, min($max, $number));
}

function money_value(mixed $value): float
{
    if (is_string($value)) {
        $value = str_replace([' ', '€', ','], ['', '', '.'], $value);
    }
    if (!is_numeric($value)) {
        return 0.0;
    }
    $number = (float) $value;
    if (!is_finite($number)) {
        return 0.0;
    }
    return round(max(0.0, min(999999999.99, $number)), 2);
}

/**
 * SQLite necesita COLLATE NOCASE para comparar correos y nombres sin
 * distinguir mayúsculas. MySQL ya usa una collation case-insensitive por
 * defecto, por lo que añadir esa cláusula sería un error de sintaxis.
 */
function sql_nocase(): string
{
    return Database::driver() === 'sqlite' ? ' COLLATE NOCASE' : '';
}

function user_is_admin(?array $user): bool
{
    return $user !== null && ($user['role'] ?? '') === 'admin';
}

/**
 * Administradores y directores pueden ver los datos de todo el equipo.
 * Solo los administradores pueden gestionar usuarios, ajustes y correo.
 */
function user_can_view_all(?array $user): bool
{
    return user_is_admin($user) || (int) ($user['is_director'] ?? 0) === 1;
}

/**
 * Añade la restricción de propietario a una consulta cuando el usuario no
 * tiene visibilidad global. Devuelve el fragmento SQL y acumula el parámetro.
 */
function ownership_scope(string $column, ?array $user, array &$params): string
{
    if (user_can_view_all($user)) {
        return '';
    }
    $params[] = (int) ($user['id'] ?? 0);
    return ' AND ' . $column . ' = ?';
}

function ownership_where(string $column, ?array $user, array &$params): ?string
{
    if (user_can_view_all($user)) {
        return null;
    }
    $params[] = (int) ($user['id'] ?? 0);
    return $column . ' = ?';
}

function require_owned_access(array $row, ?array $user, string $message = 'Registro no encontrado.'): array
{
    if (!user_can_view_all($user) && (int) ($row['owner_id'] ?? 0) !== (int) ($user['id'] ?? 0)) {
        throw new HttpError($message, 404);
    }
    return $row;
}

/**
 * Una tarea es visible para su responsable, quien la creó o el propietario del
 * lead/proyecto al que pertenece. Administradores y directores ven todas.
 */
function task_visibility_scope(string $alias, ?array $user, array &$params): string
{
    if (user_can_view_all($user)) {
        return '';
    }
    $id = (int) ($user['id'] ?? 0);
    array_push($params, $id, $id, $id, $id);
    return " AND ($alias.assigned_to = ? OR $alias.created_by = ?"
        . " OR $alias.lead_id IN (SELECT id FROM leads WHERE owner_id = ?)"
        . " OR $alias.project_id IN (SELECT id FROM projects WHERE owner_id = ?))";
}

function log_activity(string $entityType, ?int $entityId, string $action, string $description): void
{
    $user = Auth::user();
    $stmt = Database::pdo()->prepare(
        'INSERT INTO activities (user_id, entity_type, entity_id, action, description, created_at) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$user['id'] ?? null, $entityType, $entityId, $action, $description, now_utc()]);
}

function relative_path(string $path): string
{
    return '/' . ltrim($path, '/');
}
