<?php
declare(strict_types=1);

const ATTACHMENT_MAX_BYTES = 10 * 1024 * 1024;
const ATTACHMENT_MIME_MAP = [
    'pdf' => ['application/pdf'],
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png' => ['image/png'],
    'gif' => ['image/gif'],
    'webp' => ['image/webp'],
    'zip' => ['application/zip', 'application/x-zip-compressed', 'multipart/x-zip', 'application/octet-stream'],
];

function attachments_dir(): string
{
    $dir = BASE_PATH . '/data/uploads';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        throw new HttpError('No se pudo preparar la carpeta de adjuntos.', 500);
    }
    return $dir;
}

function handle_attachments(string $method, ?int $id, ?string $action, array $user): void
{
    $pdo = Database::pdo();

    if ($id === null && $method === 'GET') {
        $type = entity_type_or_fail(query_string('entity_type'));
        $entityId = query_int('entity_id', 0, 0, PHP_INT_MAX);
        if ($entityId <= 0 || !entity_exists($type, $entityId)) {
            throw new HttpError('Entidad no encontrada.', 404);
        }
        require_entity_access($type, $entityId, $user);
        json_response(attachments_for($type, $entityId));
    }

    if ($id === null && $method === 'POST') {
        $type = entity_type_or_fail($_POST['entity_type'] ?? '');
        $entityId = (int) ($_POST['entity_id'] ?? 0);
        if ($entityId <= 0 || !entity_exists($type, $entityId)) {
            throw new HttpError('Entidad no encontrada.', 404);
        }
        require_entity_access($type, $entityId, $user);
        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            throw new HttpError('Adjunta un archivo válido.', 422, ['file' => 'Selecciona un archivo.']);
        }

        $file = $_FILES['file'];
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $message = (int) ($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE
                ? 'El archivo supera el tamaño permitido por el servidor.'
                : 'No se pudo subir el archivo.';
            throw new HttpError($message, 422, ['file' => $message]);
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > ATTACHMENT_MAX_BYTES) {
            throw new HttpError('El archivo supera el límite de 10 MB.', 422, ['file' => 'Máximo 10 MB.']);
        }

        $originalName = is_string($file['name'] ?? null) ? str_replace('\\', '/', $file['name']) : 'adjunto';
        $original = clean_string(basename($originalName), 190);
        if ($original === '') {
            $original = 'adjunto';
        }
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!isset(ATTACHMENT_MIME_MAP[$extension])) {
            throw new HttpError('Tipo de archivo no permitido. Usa PDF, imágenes o ZIP.', 422, ['file' => 'Formato no permitido.']);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file((string) $file['tmp_name']);
        if (!in_array($mime, ATTACHMENT_MIME_MAP[$extension], true)) {
            throw new HttpError('El contenido del archivo no coincide con su extensión.', 422, ['file' => 'Archivo no válido.']);
        }

        if (!is_uploaded_file((string) $file['tmp_name'])) {
            throw new HttpError('El archivo recibido no es válido.', 422, ['file' => 'Archivo no válido.']);
        }

        $stored = bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = attachments_dir() . '/' . $stored;
        if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
            throw new HttpError('No se pudo guardar el archivo.', 500);
        }

        $pdo->prepare(
            'INSERT INTO attachments (entity_type, entity_id, filename, original_name, mime_type, size, uploaded_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $type,
            $entityId,
            $stored,
            $original,
            $mime,
            $size,
            (int) $user['id'],
            now_utc(),
        ]);
        $newId = (int) $pdo->lastInsertId();
        $table = entity_table($type);
        log_activity($type, $entityId, 'adjunto_subido', 'ha subido el archivo «' . $original . '»');

        $stmt = $pdo->prepare(
            'SELECT a.*, u.name AS uploaded_by_name FROM attachments a
             LEFT JOIN users u ON u.id = a.uploaded_by WHERE a.id = ?'
        );
        $stmt->execute([$newId]);
        json_response($stmt->fetch(), 201);
    }

    if ($id !== null && $method === 'GET' && $action === 'download') {
        $attachment = record_or_404('attachments', $id, 'Adjunto no encontrado.');
        require_entity_access((string) $attachment['entity_type'], (int) $attachment['entity_id'], $user);
        $path = attachments_dir() . '/' . basename((string) $attachment['filename']);
        if (!is_file($path)) {
            throw new HttpError('El archivo ya no está disponible en el servidor.', 404);
        }

        $downloadName = Security::headerFilename((string) $attachment['original_name']);
        header('Content-Type: ' . $attachment['mime_type']);
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('X-Download-Options: noopen');
        header('Content-Security-Policy: sandbox');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    if ($id !== null && $method === 'DELETE') {
        $attachment = record_or_404('attachments', $id, 'Adjunto no encontrado.');
        require_entity_access((string) $attachment['entity_type'], (int) $attachment['entity_id'], $user);
        $isAdmin = ($user['role'] ?? '') === 'admin';
        if (!$isAdmin && (int) ($attachment['uploaded_by'] ?? 0) !== (int) $user['id']) {
            throw new HttpError('No tienes permisos para eliminar este adjunto.', 403);
        }
        $path = attachments_dir() . '/' . basename((string) $attachment['filename']);
        if (is_file($path)) {
            @unlink($path);
        }
        $pdo->prepare('DELETE FROM attachments WHERE id = ?')->execute([$id]);
        log_activity((string) $attachment['entity_type'], (int) $attachment['entity_id'], 'adjunto_eliminado', 'ha eliminado el archivo «' . $attachment['original_name'] . '»');
        json_response(['message' => 'Adjunto eliminado.']);
    }

    throw new HttpError('Método no permitido.', 405);
}
