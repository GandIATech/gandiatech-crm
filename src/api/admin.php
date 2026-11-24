<?php
declare(strict_types=1);

function handle_admin(string $method, array $segments, array $user): void
{
    Auth::requireAdmin();

    $section = $segments[1] ?? '';

    if ($method === 'GET' && $section === 'export') {
        export_csv(query_string('resource'));
    }

    if ($method === 'POST' && $section === 'import-contacts') {
        import_contacts_csv($user);
    }

    if ($section === 'trash') {
        if ($method === 'GET') {
            json_response(trash_list());
        }
        if ($method === 'POST') {
            $input = json_input();
            $type = clean_string($input['type'] ?? '', 20);
            $id = int_or_null($input['id'] ?? null);
            $action = clean_string($input['action'] ?? '', 20);
            if ($id === null || !in_array($type, ['lead', 'contact', 'project', 'task'], true)) {
                throw new HttpError('Elemento de papelera no válido.', 422);
            }
            if (!in_array($action, ['restore', 'purge'], true)) {
                throw new HttpError('Acción no válida.', 422);
            }
            json_response(trash_action($type, $id, $action, $user));
        }
    }

    throw new HttpError('Acción no encontrada.', 404);
}

function export_csv(string $resource): never
{
    $pdo = Database::pdo();
    $allowed = ['leads', 'contacts', 'projects'];
    if (!in_array($resource, $allowed, true)) {
        throw new HttpError('Exportación no válida.', 422);
    }

    [$filename, $headers, $rows] = match ($resource) {
        'leads' => export_leads_rows($pdo),
        'contacts' => export_contacts_rows($pdo),
        'projects' => export_projects_rows($pdo),
    };

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');

    $output = fopen('php://output', 'w');
    if ($output === false) {
        throw new HttpError('No se pudo generar el CSV.', 500);
    }
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, $headers, ';', '"', '\\');
    foreach ($rows as $row) {
        fputcsv($output, array_map([Security::class, 'csvCell'], $row), ';', '"', '\\');
    }
    fclose($output);
    exit;
}

function export_leads_rows(PDO $pdo): array
{
    $where = ['l.deleted_at IS NULL'];
    $params = [];
    $q = query_string('q');
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = '(l.title LIKE ? OR l.company LIKE ? OR c.name LIKE ?)';
        array_push($params, $like, $like, $like);
    }
    $status = query_int('status_id', 0, 0, PHP_INT_MAX);
    if ($status > 0) {
        $where[] = 'l.status_id = ?';
        $params[] = $status;
    }
    $owner = query_int('owner_id', 0, 0, PHP_INT_MAX);
    if ($owner > 0) {
        $where[] = 'l.owner_id = ?';
        $params[] = $owner;
    }

    $stmt = $pdo->prepare(
        'SELECT l.id, l.title, c.name AS contact_name, COALESCE(l.company, c.company) AS company, l.value,
                s.name AS status_name, u.name AS owner_name, l.source, l.expected_close_date, l.notes, l.lost_reason, l.created_at
         FROM leads l
         LEFT JOIN contacts c ON c.id = l.contact_id
         LEFT JOIN lead_statuses s ON s.id = l.status_id
         LEFT JOIN users u ON u.id = l.owner_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY l.created_at DESC'
    );
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            $row['id'], $row['title'], $row['contact_name'] ?? '', $row['company'] ?? '',
            number_format((float) $row['value'], 2, ',', ''), $row['status_name'] ?? '',
            $row['owner_name'] ?? '', $row['source'] ?? '', $row['expected_close_date'] ?? '',
            $row['notes'] ?? '', $row['lost_reason'] ?? '', $row['created_at'],
        ];
    }

    return [
        'leads-' . date('Y-m-d') . '.csv',
        ['ID', 'Título', 'Contacto', 'Empresa', 'Valor (€)', 'Estado', 'Responsable', 'Origen', 'Cierre previsto', 'Notas', 'Motivo de pérdida', 'Creado'],
        $rows,
    ];
}

function export_contacts_rows(PDO $pdo): array
{
    $where = ['c.deleted_at IS NULL'];
    $params = [];
    $q = query_string('q');
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = '(c.name LIKE ? OR c.email LIKE ? OR c.company LIKE ? OR c.phone LIKE ?)';
        array_push($params, $like, $like, $like, $like);
    }
    $owner = query_int('owner_id', 0, 0, PHP_INT_MAX);
    if ($owner > 0) {
        $where[] = 'c.owner_id = ?';
        $params[] = $owner;
    }

    $stmt = $pdo->prepare(
        'SELECT c.*, u.name AS owner_name FROM contacts c
         LEFT JOIN users u ON u.id = c.owner_id
         WHERE ' . implode(' AND ', $where) . ' ORDER BY c.name'
    );
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            $row['id'], $row['name'], $row['email'] ?? '', $row['phone'] ?? '', $row['company'] ?? '',
            $row['position'] ?? '', $row['notes'] ?? '', $row['owner_name'] ?? '', $row['created_at'],
        ];
    }

    return [
        'contactos-' . date('Y-m-d') . '.csv',
        ['ID', 'Nombre', 'Correo', 'Teléfono', 'Empresa', 'Cargo', 'Notas', 'Responsable', 'Creado'],
        $rows,
    ];
}

function export_projects_rows(PDO $pdo): array
{
    $where = ['p.deleted_at IS NULL'];
    $params = [];
    $q = query_string('q');
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = '(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)';
        array_push($params, $like, $like, $like);
    }
    $status = query_string('status');
    if (in_array($status, ['planificado', 'en_progreso', 'en_pausa', 'completado', 'cancelado'], true)) {
        $where[] = 'p.status = ?';
        $params[] = $status;
    }
    $owner = query_int('owner_id', 0, 0, PHP_INT_MAX);
    if ($owner > 0) {
        $where[] = 'p.owner_id = ?';
        $params[] = $owner;
    }

    $stmt = $pdo->prepare(
        'SELECT p.*, c.name AS contact_name, l.title AS lead_title, u.name AS owner_name,
                (SELECT COALESCE(SUM(te.hours), 0) FROM time_entries te WHERE te.project_id = p.id) AS hours_logged
         FROM projects p
         LEFT JOIN contacts c ON c.id = p.contact_id
         LEFT JOIN leads l ON l.id = p.lead_id
         LEFT JOIN users u ON u.id = p.owner_id
         WHERE ' . implode(' AND ', $where) . ' ORDER BY p.created_at DESC'
    );
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            $row['id'], $row['name'], $row['contact_name'] ?? '', $row['lead_title'] ?? '', $row['status'],
            number_format((float) $row['budget'], 2, ',', ''),
            number_format((float) $row['budgeted_hours'], 2, ',', ''),
            number_format((float) $row['hours_logged'], 2, ',', ''),
            $row['owner_name'] ?? '', $row['start_date'] ?? '', $row['end_date'] ?? '',
            (int) $row['progress'], $row['description'] ?? '',
        ];
    }

    return [
        'proyectos-' . date('Y-m-d') . '.csv',
        ['ID', 'Nombre', 'Contacto', 'Lead', 'Estado', 'Presupuesto (€)', 'Horas presupuestadas', 'Horas reales', 'Responsable', 'Inicio', 'Fin', 'Avance (%)', 'Descripción'],
        $rows,
    ];
}

function import_contacts_csv(array $user): never
{
    if (!isset($_FILES['file']) || !is_array($_FILES['file']) || (int) ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new HttpError('Selecciona un archivo CSV válido.', 422, ['file' => 'Archivo CSV requerido.']);
    }
    $tmp = (string) $_FILES['file']['tmp_name'];
    $size = (int) ($_FILES['file']['size'] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        throw new HttpError('El CSV debe pesar menos de 5 MB.', 422, ['file' => 'Máximo 5 MB.']);
    }
    if (!is_uploaded_file($tmp)) {
        throw new HttpError('El archivo recibido no es válido.', 422, ['file' => 'Archivo no válido.']);
    }
    $uploadName = is_string($_FILES['file']['name'] ?? null) ? $_FILES['file']['name'] : '';
    $extension = strtolower(pathinfo($uploadName, PATHINFO_EXTENSION));
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $uploadMime = (string) $finfo->file($tmp);
    $allowedMimes = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
    if ($extension !== 'csv' || !in_array($uploadMime, $allowedMimes, true)) {
        throw new HttpError('El archivo debe estar en formato CSV.', 422, ['file' => 'Formato no válido.']);
    }

    $handle = fopen($tmp, 'rb');
    if ($handle === false) {
        throw new HttpError('No se pudo leer el CSV.', 422);
    }

    $firstLine = (string) fgets($handle);
    $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine) ?? $firstLine;
    $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
    rewind($handle);

    $header = fgetcsv($handle, 0, $delimiter);
    if (!is_array($header) || $header === []) {
        fclose($handle);
        throw new HttpError('El CSV no tiene cabecera.', 422);
    }
    $map = [];
    $mappingRaw = $_POST['mapping'] ?? '';
    $mapping = is_string($mappingRaw) && $mappingRaw !== '' ? json_decode($mappingRaw, true) : null;
    if (is_array($mapping)) {
        foreach (['name', 'email', 'phone', 'company', 'position', 'notes'] as $key) {
            if (isset($mapping[$key]) && is_numeric($mapping[$key])) {
                $index = (int) $mapping[$key];
                if ($index >= 0 && $index < count($header)) {
                    $map[$key] = $index;
                }
            }
        }
    } else {
        foreach ($header as $index => $label) {
            $key = import_header_key((string) $label);
            if ($key !== null) {
                $map[$key] = $index;
            }
        }
    }
    if (!isset($map['name'])) {
        fclose($handle);
        throw new HttpError('El CSV debe incluir una columna de nombre.', 422);
    }

    $pdo = Database::pdo();
    $exists = $pdo->prepare('SELECT id FROM contacts WHERE email = ?' . sql_nocase() . ' LIMIT 1');
    $insert = $pdo->prepare(
        'INSERT INTO contacts (name, email, phone, company, position, notes, owner_id, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $imported = 0;
    $skipped = 0;
    $errors = [];
    $seen = [];
    $line = 1;

    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $line++;
        if ($line > 5001) {
            fclose($handle);
            throw new HttpError('El CSV supera el máximo de 5.000 contactos por importación.', 422);
        }
        if ($row === [null] || $row === []) {
            continue;
        }
        $value = static function (string $key) use ($row, $map): string {
            if (!isset($map[$key])) {
                return '';
            }
            return clean_string($row[$map[$key]] ?? '', $key === 'notes' ? 3000 : 190);
        };

        $name = $value('name');
        if ($name === '') {
            $skipped++;
            if (count($errors) < 20) {
                $errors[] = 'Línea ' . $line . ': falta el nombre.';
            }
            continue;
        }
        $email = strtolower($value('email'));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $skipped++;
            if (count($errors) < 20) {
                $errors[] = 'Línea ' . $line . ': correo no válido.';
            }
            continue;
        }
        if ($email !== '') {
            if (isset($seen[$email])) {
                $skipped++;
                if (count($errors) < 20) {
                    $errors[] = 'Línea ' . $line . ': correo duplicado en el archivo.';
                }
                continue;
            }
            $exists->execute([$email]);
            if ($exists->fetch()) {
                $seen[$email] = true;
                $skipped++;
                if (count($errors) < 20) {
                    $errors[] = 'Línea ' . $line . ': ya existe un contacto con ese correo.';
                }
                continue;
            }
            $seen[$email] = true;
        }

        $insert->execute([
            $name,
            $email !== '' ? $email : null,
            $value('phone'),
            $value('company'),
            $value('position'),
            $value('notes'),
            (int) $user['id'],
            now_utc(),
            now_utc(),
        ]);
        $imported++;
    }
    fclose($handle);

    log_activity('contact', null, 'contactos_importados', 'ha importado ' . $imported . ' contactos desde CSV');
    json_response([
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => $errors,
    ]);
}

function import_header_key(string $label): ?string
{
    $normalized = strtolower(trim($label));
    $normalized = strtr($normalized, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
    $normalized = preg_replace('/[^a-z0-9]+/', '', $normalized) ?? '';
    $map = [
        'nombre' => 'name', 'name' => 'name', 'nombres' => 'name', 'nombreyapellidos' => 'name',
        'correo' => 'email', 'email' => 'email', 'correoelectronico' => 'email', 'mail' => 'email',
        'telefono' => 'phone', 'phone' => 'phone', 'movil' => 'phone', 'tel' => 'phone',
        'empresa' => 'company', 'company' => 'company', 'organizacion' => 'company',
        'cargo' => 'position', 'position' => 'position', 'puesto' => 'position',
        'notas' => 'notes', 'notes' => 'notes', 'comentarios' => 'notes', 'observaciones' => 'notes',
    ];
    return $map[$normalized] ?? null;
}

function trash_list(): array
{
    $pdo = Database::pdo();
    $items = [];
    $sources = [
        ['lead', 'leads', 'title'],
        ['contact', 'contacts', 'name'],
        ['project', 'projects', 'name'],
        ['task', 'tasks', 'title'],
    ];
    foreach ($sources as [$type, $table, $label]) {
        $rows = $pdo->query(
            "SELECT id, $label AS label, deleted_at FROM $table WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC, id DESC LIMIT 200"
        )->fetchAll();
        foreach ($rows as $row) {
            $items[] = [
                'type' => $type,
                'type_label' => ['lead' => 'Lead', 'contact' => 'Contacto', 'project' => 'Proyecto', 'task' => 'Tarea'][$type],
                'id' => (int) $row['id'],
                'label' => (string) ($row['label'] ?? ''),
                'deleted_at' => $row['deleted_at'],
            ];
        }
    }
    usort($items, static fn (array $a, array $b): int => strcmp((string) $b['deleted_at'], (string) $a['deleted_at']));
    return $items;
}

function trash_action(string $type, int $id, string $action, array $user): array
{
    $pdo = Database::pdo();
    $table = ['lead' => 'leads', 'contact' => 'contacts', 'project' => 'projects', 'task' => 'tasks'][$type];
    $row = record_or_404($table, $id, 'Elemento no encontrado.', true);
    if (empty($row['deleted_at'])) {
        throw new HttpError('Ese elemento no está en la papelera.', 409);
    }

    if ($action === 'restore') {
        $pdo->prepare("UPDATE $table SET deleted_at = NULL, updated_at = ? WHERE id = ?")
            ->execute([now_utc(), $id]);
        log_activity($type, $id, 'restaurado', 'ha restaurado «' . ($row['title'] ?? $row['name'] ?? '') . '» desde la papelera');
        return ['message' => 'Elemento restaurado.'];
    }

    if ($type === 'lead' || $type === 'project') {
        // Las tareas hijas se eliminan por clave foránea en cascada, pero sus
        // adjuntos no: hay que purgarlos antes de borrar la entidad padre.
        $foreignCol = $type === 'project' ? 'project_id' : 'lead_id';
        $stmt = $pdo->prepare("SELECT id FROM tasks WHERE $foreignCol = ?");
        $stmt->execute([$id]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $childTaskId) {
            purge_attachments('task', (int) $childTaskId);
        }
        purge_attachments($type, $id);
    } elseif ($type === 'task') {
        purge_attachments($type, $id);
    }
    $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
    log_activity($type, $id, 'eliminado_definitivo', 'ha eliminado definitivamente «' . ($row['title'] ?? $row['name'] ?? '') . '»');
    return ['message' => 'Elemento eliminado definitivamente.'];
}

function purge_attachments(string $type, int $id): void
{
    $pdo = Database::pdo();
    $stmt = $pdo->prepare('SELECT id, filename FROM attachments WHERE entity_type = ? AND entity_id = ?');
    $stmt->execute([$type, $id]);
    foreach ($stmt->fetchAll() as $attachment) {
        $path = BASE_PATH . '/data/uploads/' . basename((string) $attachment['filename']);
        if (is_file($path)) {
            @unlink($path);
        }
    }
    $pdo->prepare('DELETE FROM attachments WHERE entity_type = ? AND entity_id = ?')->execute([$type, $id]);
}
