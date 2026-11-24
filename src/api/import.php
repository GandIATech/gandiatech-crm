<?php
declare(strict_types=1);

/**
 * Importación de CSV para contactos y leads.
 *
 * La primera fila del archivo son los encabezados. El cliente envía el mapeo
 * (campo de la base de datos → índice de columna) y aquí se validan los valores
 * fila a fila con las mismas reglas que el alta manual.
 */
const IMPORT_MAX_BYTES = 5 * 1024 * 1024;
const IMPORT_MAX_ROWS = 5000;
const IMPORT_MAX_ERRORS = 20;

function import_kind_fields(string $kind): array
{
    return $kind === 'leads'
        ? ['title', 'company', 'value', 'status', 'source', 'expected_close_date', 'notes', 'contact_email']
        : ['name', 'name_secondary', 'email', 'phone', 'company', 'position', 'notes'];
}

function import_header_hints(string $kind): array
{
    if ($kind === 'leads') {
        return [
            'title' => ['titulo', 'title', 'lead', 'oportunidad', 'asunto', 'negocio'],
            'company' => ['empresa', 'company', 'organizacion', 'compania'],
            'value' => ['valor', 'importe', 'value', 'cuantia', 'presupuesto', 'amount'],
            'status' => ['estado', 'status', 'fase', 'etapa'],
            'source' => ['origen', 'source', 'fuente', 'canal'],
            'expected_close_date' => ['cierreprevisto', 'fechacierre', 'cierre', 'expectedclosedate', 'fechaestimada'],
            'contact_email' => ['correocontacto', 'emailcontacto', 'contactemail', 'correo', 'email', 'mail'],
            'notes' => ['notas', 'notes', 'comentarios', 'observaciones', 'descripcion'],
        ];
    }

    return [
        'name' => ['nombre', 'name', 'contacto', 'cliente', 'nombreyapellidos'],
        'name_secondary' => ['apellidos', 'apellido', 'surname', 'lastname', 'lastnames'],
        'email' => ['correo', 'email', 'mail', 'correoelectronico'],
        'phone' => ['telefono', 'phone', 'movil', 'tlf', 'tel'],
        'company' => ['empresa', 'company', 'organizacion', 'compania'],
        'position' => ['cargo', 'position', 'puesto', 'rol'],
        'notes' => ['notas', 'notes', 'comentarios', 'observaciones'],
    ];
}

function import_normalize_label(string $label): string
{
    $text = text_lower(trim($label));
    $text = strtr($text, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'ç' => 'c',
    ]);
    return preg_replace('/[^a-z0-9]+/', '', $text) ?? '';
}

/** Propone un mapeo a partir de los encabezados del archivo. */
function import_suggest_mapping(array $headers, string $kind): array
{
    $hints = import_header_hints($kind);
    $normalized = array_map('import_normalize_label', $headers);
    $mapping = [];

    foreach ($normalized as $index => $label) {
        if ($label === '') {
            continue;
        }
        foreach ($hints as $field => $words) {
            if (!isset($mapping[$field]) && in_array($label, $words, true)) {
                $mapping[$field] = $index;
            }
        }
    }

    foreach ($normalized as $index => $label) {
        if ($label === '') {
            continue;
        }
        foreach ($hints as $field => $words) {
            if (isset($mapping[$field])) {
                continue;
            }
            foreach ($words as $word) {
                if (text_length($word) >= 4 && str_contains($label, $word)) {
                    $mapping[$field] = $index;
                    continue 2;
                }
            }
        }
    }

    if (isset($mapping['name_secondary'])) {
        if (isset($mapping['name'])) {
            // «Nombre y apellidos» en una sola columna ya es el nombre completo.
            if ($mapping['name_secondary'] === $mapping['name']) {
                unset($mapping['name_secondary']);
            }
        } else {
            // Si solo hay apellidos, ese valor pasa a ser el nombre del contacto.
            $mapping['name'] = $mapping['name_secondary'];
            unset($mapping['name_secondary']);
        }
    }

    return $mapping;
}

/** Une «Nombre» y «Apellidos» en el único campo de nombre del contacto. */
function import_contact_name(string $name, string $lastName): string
{
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    $lastName = trim(preg_replace('/\s+/u', ' ', $lastName) ?? $lastName);
    if ($lastName === '') {
        return clean_string($name, 120);
    }
    if ($name === '') {
        return clean_string($lastName, 120);
    }
    // Evita duplicar el apellido si el nombre ya lo incluye.
    if (str_ends_with(text_lower($name), text_lower($lastName))) {
        return clean_string($name, 120);
    }
    return clean_string($name . ' ' . $lastName, 120);
}

function import_cell(mixed $value, int $max): string
{
    $text = is_scalar($value) ? (string) $value : '';
    if ($text !== '' && preg_match('//u', $text) !== 1) {
        if (function_exists('mb_convert_encoding')) {
            $text = (string) mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        } elseif (function_exists('iconv')) {
            $text = (string) iconv('Windows-1252', 'UTF-8//TRANSLIT', $text);
        }
    }
    return clean_string($text, $max);
}

function import_row_empty(array $row): bool
{
    foreach ($row as $cell) {
        if (is_scalar($cell) && trim((string) $cell) !== '') {
            return false;
        }
    }
    return true;
}

function import_money(string $value): float
{
    $text = str_replace([' ', "\u{00A0}", '€', '$', 'EUR', 'eur'], '', $value);
    if ($text === '') {
        return 0.0;
    }
    $lastComma = strrpos($text, ',');
    $lastDot = strrpos($text, '.');
    if ($lastComma !== false && $lastDot !== false) {
        if ($lastComma > $lastDot) {
            $text = str_replace(',', '.', str_replace('.', '', $text));
        } else {
            $text = str_replace(',', '', $text);
        }
    } elseif ($lastComma !== false) {
        $decimals = strlen(substr($text, $lastComma + 1));
        $text = $decimals <= 2 ? str_replace(',', '.', $text) : str_replace(',', '', $text);
    }
    return money_value($text);
}

function import_date(string $value): ?string
{
    $text = trim($value);
    if ($text === '') {
        return null;
    }
    $iso = valid_date(substr($text, 0, 10));
    if ($iso !== null) {
        return $iso;
    }
    if (preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{2,4})$#', $text, $parts) === 1) {
        $year = (int) $parts[3];
        if ($year < 100) {
            $year += 2000;
        }
        return valid_date(sprintf('%04d-%02d-%02d', $year, (int) $parts[2], (int) $parts[1]));
    }
    return null;
}

function import_owner_id(array $user): int
{
    $requested = int_or_null($_POST['owner_id'] ?? null);
    if ($requested !== null && user_can_view_all($user)) {
        $valid = ensure_user($requested);
        if ($valid !== null) {
            return $valid;
        }
    }
    return (int) $user['id'];
}

function import_detect_delimiter(string $line): string
{
    $candidates = [';', ',', "\t", '|'];
    $best = ';';
    $bestCount = 0;
    foreach ($candidates as $candidate) {
        $count = substr_count($line, $candidate);
        if ($count > $bestCount) {
            $best = $candidate;
            $bestCount = $count;
        }
    }
    return $best;
}

function import_open_upload(): array
{
    if (!isset($_FILES['file']) || !is_array($_FILES['file']) || (int) ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new HttpError('Selecciona un archivo CSV válido.', 422, ['file' => 'Archivo CSV requerido.']);
    }
    $tmp = (string) $_FILES['file']['tmp_name'];
    $size = (int) ($_FILES['file']['size'] ?? 0);
    if ($size <= 0 || $size > IMPORT_MAX_BYTES) {
        throw new HttpError('El CSV debe pesar menos de 5 MB.', 422, ['file' => 'Máximo 5 MB.']);
    }
    if (!is_uploaded_file($tmp)) {
        throw new HttpError('El archivo recibido no es válido.', 422, ['file' => 'Archivo no válido.']);
    }
    $uploadName = is_string($_FILES['file']['name'] ?? null) ? $_FILES['file']['name'] : '';
    $extension = strtolower(pathinfo($uploadName, PATHINFO_EXTENSION));
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $uploadMime = (string) $finfo->file($tmp);
    $allowedMimes = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'text/x-csv'];
    if ($extension !== 'csv' || !in_array($uploadMime, $allowedMimes, true)) {
        throw new HttpError('El archivo debe estar en formato CSV.', 422, ['file' => 'Formato no válido.']);
    }

    return [$tmp, $uploadName];
}

function import_read_mapping(array $headers, string $kind): array
{
    $raw = $_POST['mapping'] ?? '';
    if (!is_string($raw) || $raw === '') {
        return import_suggest_mapping($headers, $kind);
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new HttpError('El mapeo de columnas no es válido.', 422);
    }
    $mapping = [];
    foreach (import_kind_fields($kind) as $field) {
        if (!isset($decoded[$field]) || !is_numeric($decoded[$field])) {
            continue;
        }
        $index = (int) $decoded[$field];
        if ($index >= 0 && $index < count($headers)) {
            $mapping[$field] = $index;
        }
    }
    if (isset($mapping['name_secondary'], $mapping['name']) && $mapping['name_secondary'] === $mapping['name']) {
        unset($mapping['name_secondary']);
    }
    return $mapping;
}

function import_require_field(array $mapping, string $field, string $message, ?string $alternative = null): void
{
    if (isset($mapping[$field])) {
        return;
    }
    if ($alternative !== null && isset($mapping[$alternative])) {
        return;
    }
    throw new HttpError($message, 422, ['mapping' => $message]);
}

function handle_import(string $method, string $kind, array $user): never
{
    if ($method !== 'POST') {
        throw new HttpError('Método no permitido.', 405);
    }
    if (!in_array($kind, ['contacts', 'leads'], true)) {
        throw new HttpError('Recurso no encontrado.', 404);
    }

    [$tmp] = import_open_upload();

    $handle = fopen($tmp, 'rb');
    if ($handle === false) {
        throw new HttpError('No se pudo leer el CSV.', 422);
    }

    try {
        $firstLine = (string) fgets($handle);
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine) ?? $firstLine;
        $delimiter = import_detect_delimiter($firstLine);
        rewind($handle);

        $headerRow = fgetcsv($handle, 0, $delimiter, '"', '\\');
        if (!is_array($headerRow) || $headerRow === [] || $headerRow === [null]) {
            throw new HttpError('El CSV no tiene cabecera.', 422, ['file' => 'La primera fila debe contener los encabezados.']);
        }
        $headers = [];
        foreach ($headerRow as $index => $label) {
            $clean = import_cell($label, 80);
            $headers[] = $clean !== '' ? $clean : 'Columna ' . ($index + 1);
        }

        $mapping = import_read_mapping($headers, $kind);

        if (($_POST['preview'] ?? '') === '1') {
            $preview = [];
            $total = 0;
            while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                if (!is_array($row) || import_row_empty($row)) {
                    continue;
                }
                $total++;
                if ($total > IMPORT_MAX_ROWS) {
                    break;
                }
                if (count($preview) < 5) {
                    $cells = [];
                    foreach ($headers as $index => $ignored) {
                        $cells[] = import_cell($row[$index] ?? '', 200);
                    }
                    $preview[] = $cells;
                }
            }
            json_response([
                'headers' => $headers,
                'delimiter' => $delimiter === "\t" ? 'tabulador' : $delimiter,
                'suggested' => $mapping,
                'preview' => $preview,
                'rows' => $total,
            ]);
        }

        import_require_field(
            $mapping,
            $kind === 'leads' ? 'title' : 'name',
            $kind === 'leads' ? 'Indica qué columna contiene el título del lead.' : 'Indica qué columna contiene el nombre del contacto.',
            $kind === 'leads' ? null : 'name_secondary'
        );

        $result = $kind === 'leads'
            ? import_leads_rows($handle, $delimiter, $mapping, $user)
            : import_contacts_rows($handle, $delimiter, $mapping, $user);

        json_response($result);
    } finally {
        fclose($handle);
    }
}

function import_contacts_rows($handle, string $delimiter, array $mapping, array $user): array
{
    $pdo = Database::pdo();
    $ownerId = import_owner_id($user);
    $updateExisting = ($_POST['duplicates'] ?? 'skip') === 'update';

    $scopeSql = '';
    $scopeParams = [];
    if (!user_can_view_all($user)) {
        $scopeSql = ' AND owner_id = ?';
        $scopeParams[] = (int) $user['id'];
    }

    $find = $pdo->prepare(
        'SELECT id FROM contacts WHERE email = ?' . sql_nocase() . ' AND deleted_at IS NULL' . $scopeSql . ' LIMIT 1'
    );
    $insert = $pdo->prepare(
        'INSERT INTO contacts (name, email, phone, company, position, notes, owner_id, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $update = $pdo->prepare(
        'UPDATE contacts SET name = ?, email = ?, phone = ?, company = ?, position = ?, notes = ?, updated_at = ? WHERE id = ?'
    );

    $imported = 0;
    $updated = 0;
    $skipped = 0;
    $errors = [];
    $seen = [];
    $line = 1;

    $pdo->beginTransaction();
    try {
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $line++;
            if ($line > IMPORT_MAX_ROWS + 1) {
                throw new HttpError('El CSV supera el máximo de 5.000 contactos por importación.', 422);
            }
            if (!is_array($row) || import_row_empty($row)) {
                continue;
            }
            $value = static function (string $field, int $max) use ($row, $mapping): string {
                return isset($mapping[$field]) ? import_cell($row[$mapping[$field]] ?? '', $max) : '';
            };

            $name = import_contact_name($value('name', 120), $value('name_secondary', 120));
            if ($name === '') {
                $skipped++;
                if (count($errors) < IMPORT_MAX_ERRORS) {
                    $errors[] = 'Línea ' . $line . ': falta el nombre.';
                }
                continue;
            }
            $email = text_lower($value('email', 190));
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                if (count($errors) < IMPORT_MAX_ERRORS) {
                    $errors[] = 'Línea ' . $line . ': el correo no es válido.';
                }
                continue;
            }
            if ($email !== '') {
                if (isset($seen[$email])) {
                    $skipped++;
                    if (count($errors) < IMPORT_MAX_ERRORS) {
                        $errors[] = 'Línea ' . $line . ': correo duplicado en el archivo.';
                    }
                    continue;
                }
                $seen[$email] = true;
            }

            $phone = $value('phone', 40);
            $company = $value('company', 160);
            $position = $value('position', 120);
            $notes = $value('notes', 3000);

            $existingId = null;
            if ($email !== '') {
                $find->execute(array_merge([$email], $scopeParams));
                $existing = $find->fetchColumn();
                $existingId = $existing !== false ? (int) $existing : null;
            }

            if ($existingId !== null) {
                if (!$updateExisting) {
                    $skipped++;
                    if (count($errors) < IMPORT_MAX_ERRORS) {
                        $errors[] = 'Línea ' . $line . ': ya existe un contacto con ese correo.';
                    }
                    continue;
                }
                $update->execute([$name, $email, $phone, $company, $position, $notes, now_utc(), $existingId]);
                $updated++;
                continue;
            }

            $insert->execute([
                $name,
                $email !== '' ? $email : null,
                $phone,
                $company,
                $position,
                $notes,
                $ownerId,
                now_utc(),
                now_utc(),
            ]);
            $imported++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    log_activity(
        'contact',
        null,
        'contactos_importados',
        'ha importado ' . $imported . ' contactos desde CSV'
            . ($updated > 0 ? ' y ha actualizado ' . $updated : '')
            . ($skipped > 0 ? ' (' . $skipped . ' omitidos)' : '')
    );

    return ['imported' => $imported, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors, 'warnings' => []];
}

function import_leads_rows($handle, string $delimiter, array $mapping, array $user): array
{
    $pdo = Database::pdo();
    $ownerId = import_owner_id($user);

    $statusRows = $pdo->query('SELECT id, name FROM lead_statuses ORDER BY position, id')->fetchAll();
    $statusByName = [];
    $defaultStatusId = null;
    $defaultStatusName = '';
    foreach ($statusRows as $status) {
        $statusId = (int) $status['id'];
        $statusByName[text_lower((string) $status['name'])] = $statusId;
        if ($defaultStatusId === null) {
            $defaultStatusId = $statusId;
            $defaultStatusName = (string) $status['name'];
        }
    }

    $contactScope = '';
    $contactScopeParams = [];
    if (!user_can_view_all($user)) {
        $contactScope = ' AND owner_id = ?';
        $contactScopeParams[] = (int) $user['id'];
    }
    $findContact = $pdo->prepare(
        'SELECT id FROM contacts WHERE email = ?' . sql_nocase() . ' AND deleted_at IS NULL' . $contactScope . ' LIMIT 1'
    );

    $insert = $pdo->prepare(
        'INSERT INTO leads (title, contact_id, company, value, status_id, source, owner_id, expected_close_date, notes, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $imported = 0;
    $skipped = 0;
    $errors = [];
    $warnings = [];
    $line = 1;

    $pdo->beginTransaction();
    try {
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $line++;
            if ($line > IMPORT_MAX_ROWS + 1) {
                throw new HttpError('El CSV supera el máximo de 5.000 leads por importación.', 422);
            }
            if (!is_array($row) || import_row_empty($row)) {
                continue;
            }
            $value = static function (string $field, int $max) use ($row, $mapping): string {
                return isset($mapping[$field]) ? import_cell($row[$mapping[$field]] ?? '', $max) : '';
            };

            $title = $value('title', 160);
            if ($title === '') {
                $skipped++;
                if (count($errors) < IMPORT_MAX_ERRORS) {
                    $errors[] = 'Línea ' . $line . ': falta el título del lead.';
                }
                continue;
            }

            $dateRaw = $value('expected_close_date', 30);
            $expected = null;
            if ($dateRaw !== '') {
                $expected = import_date($dateRaw);
                if ($expected === null) {
                    $skipped++;
                    if (count($errors) < IMPORT_MAX_ERRORS) {
                        $errors[] = 'Línea ' . $line . ': la fecha de cierre no es válida.';
                    }
                    continue;
                }
            }

            $statusId = $defaultStatusId;
            $statusRaw = $value('status', 60);
            if ($statusRaw !== '') {
                $lookup = text_lower($statusRaw);
                if (isset($statusByName[$lookup])) {
                    $statusId = $statusByName[$lookup];
                } elseif (count($warnings) < IMPORT_MAX_ERRORS) {
                    $warnings[] = 'Línea ' . $line . ': el estado «' . $statusRaw . '» no existe; se usa «'
                        . ($defaultStatusName !== '' ? $defaultStatusName : 'sin estado') . '».';
                }
            }

            $contactId = null;
            $contactEmail = text_lower($value('contact_email', 190));
            if ($contactEmail !== '') {
                if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
                    if (count($warnings) < IMPORT_MAX_ERRORS) {
                        $warnings[] = 'Línea ' . $line . ': el correo del contacto no es válido; el lead se importa sin contacto.';
                    }
                } else {
                    $findContact->execute(array_merge([$contactEmail], $contactScopeParams));
                    $contact = $findContact->fetchColumn();
                    if ($contact !== false) {
                        $contactId = (int) $contact;
                    } elseif (count($warnings) < IMPORT_MAX_ERRORS) {
                        $warnings[] = 'Línea ' . $line . ': no existe ningún contacto con «' . $contactEmail . '»; el lead se importa sin contacto.';
                    }
                }
            }

            $insert->execute([
                $title,
                $contactId,
                $value('company', 160),
                import_money($value('value', 40)),
                $statusId,
                $value('source', 80),
                $ownerId,
                $expected,
                $value('notes', 5000),
                now_utc(),
                now_utc(),
            ]);
            $imported++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    log_activity(
        'lead',
        null,
        'leads_importados',
        'ha importado ' . $imported . ' leads desde CSV' . ($skipped > 0 ? ' (' . $skipped . ' omitidos)' : '')
    );

    return ['imported' => $imported, 'updated' => 0, 'skipped' => $skipped, 'errors' => $errors, 'warnings' => $warnings];
}
