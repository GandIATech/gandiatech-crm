<?php
declare(strict_types=1);

const UPDATE_CACHE_TTL = 12 * 60 * 60;
const UPDATE_MAX_MANIFEST_BYTES = 65536;
const UPDATE_MAX_SIGNATURE_BYTES = 8192;
const UPDATE_MAX_PACKAGE_BYTES = 50 * 1024 * 1024;

function handle_updates(string $method, ?string $action, array $user): void
{
    Auth::requireAdmin();

    if ($method === 'GET' && $action === 'check') {
        json_response(updates_check());
    }

    if ($method === 'GET' && $action === 'download') {
        updates_download();
    }

    throw new HttpError('Acción no encontrada.', 404);
}

function updates_check(): array
{
    $result = updates_manifest(query_string('force') === '1');
    $manifest = $result['manifest'];
    $minPhp = (string) ($manifest['min_php'] ?? '8.2');

    return [
        'current' => APP_VERSION,
        'latest' => (string) $manifest['version'],
        'available' => version_compare((string) $manifest['version'], APP_VERSION, '>'),
        'min_php' => $minPhp,
        'php_ok' => version_compare(PHP_VERSION, $minPhp, '>='),
        'current_php' => PHP_VERSION,
        'current_db_version' => Database::schemaVersion(),
        'db_version' => isset($manifest['db_version']) ? (int) $manifest['db_version'] : null,
        'size' => (int) $manifest['size'],
        'sha256' => (string) $manifest['sha256'],
        'changelog_url' => (string) ($manifest['changelog_url'] ?? ''),
        'checked_at' => gmdate('Y-m-d\TH:i:s\Z', $result['checked_at']),
        'cached' => $result['cached'],
        'warning' => $result['warning'],
    ];
}

/**
 * Devuelve el manifiesto verificado. Usa caché de 12 horas y, si la red falla,
 * la última copia válida guardada.
 */
function updates_manifest(bool $force = false): array
{
    $cacheFile = BASE_PATH . '/data/update-check.json';
    $cache = updates_read_cache($cacheFile);
    $now = time();

    if (!$force && $cache !== null && $now - $cache['checked_at'] < UPDATE_CACHE_TTL) {
        try {
            return [
                'manifest' => updates_verify_manifest($cache['raw'], $cache['sig']),
                'checked_at' => $cache['checked_at'],
                'cached' => true,
                'warning' => null,
            ];
        } catch (Throwable $e) {
            // Caché manipulada o incompatible: se ignora y se descarga de nuevo.
        }
    }

    try {
        $raw = update_http_get(UPDATE_MANIFEST_URL, UPDATE_MAX_MANIFEST_BYTES);
        $signature = update_http_get(UPDATE_MANIFEST_URL . '.sig', UPDATE_MAX_SIGNATURE_BYTES);
        $manifest = updates_verify_manifest($raw, $signature);
        @file_put_contents(
            $cacheFile,
            json_encode(['checked_at' => $now, 'raw' => $raw, 'sig' => trim($signature)], JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );

        return ['manifest' => $manifest, 'checked_at' => $now, 'cached' => false, 'warning' => null];
    } catch (Throwable $e) {
        error_log('[CRM] Comprobación de actualizaciones: ' . $e->getMessage());
        if ($cache !== null) {
            try {
                return [
                    'manifest' => updates_verify_manifest($cache['raw'], $cache['sig']),
                    'checked_at' => $cache['checked_at'],
                    'cached' => true,
                    'warning' => 'No se pudo consultar el servidor de actualizaciones; se muestra la última comprobación guardada.',
                ];
            } catch (Throwable $ignored) {
                // La caché tampoco es válida.
            }
        }
        throw new HttpError('No se pudo consultar el servidor de actualizaciones. Inténtalo de nuevo más tarde.', 502);
    }
}

function updates_read_cache(string $file): ?array
{
    if (!is_file($file)) {
        return null;
    }
    $data = json_decode((string) @file_get_contents($file), true);
    if (!is_array($data) || !isset($data['checked_at'], $data['raw'], $data['sig'])) {
        return null;
    }
    return [
        'checked_at' => (int) $data['checked_at'],
        'raw' => (string) $data['raw'],
        'sig' => (string) $data['sig'],
    ];
}

function updates_verify_manifest(string $raw, string $signatureBase64): array
{
    if (!function_exists('sodium_crypto_sign_verify_detached')) {
        throw new RuntimeException('La extensión sodium no está disponible.');
    }

    $publicKey = base64_decode((string) UPDATE_PUBLIC_KEY, true);
    $signature = base64_decode(trim($signatureBase64), true);
    if ($publicKey === false || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
        throw new RuntimeException('La clave pública de actualizaciones no es válida.');
    }
    if ($signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
        throw new RuntimeException('La firma del manifiesto no es válida.');
    }
    if (!sodium_crypto_sign_verify_detached($signature, $raw, $publicKey)) {
        throw new RuntimeException('La firma del manifiesto no coincide con la clave pública.');
    }

    try {
        $manifest = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('El manifiesto no es JSON válido.');
    }
    if (!is_array($manifest)) {
        throw new RuntimeException('El manifiesto no es válido.');
    }

    $version = (string) ($manifest['version'] ?? '');
    if (preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $version) !== 1) {
        throw new RuntimeException('La versión del manifiesto no es válida.');
    }

    $sha256 = strtolower((string) ($manifest['sha256'] ?? ''));
    if (preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1) {
        throw new RuntimeException('El checksum del manifiesto no es válido.');
    }

    $size = (int) ($manifest['size'] ?? 0);
    if ($size <= 0 || $size > UPDATE_MAX_PACKAGE_BYTES) {
        throw new RuntimeException('El tamaño del paquete no es válido.');
    }

    $downloadUrl = (string) ($manifest['download_url'] ?? '');
    if (!updates_allowed_url($downloadUrl)) {
        throw new RuntimeException('La URL de descarga no es válida.');
    }

    $changelogUrl = (string) ($manifest['changelog_url'] ?? '');
    if ($changelogUrl !== '' && !updates_allowed_url($changelogUrl)) {
        throw new RuntimeException('La URL del changelog no es válida.');
    }

    $manifest['sha256'] = $sha256;
    $manifest['size'] = $size;

    return $manifest;
}

function updates_allowed_url(string $url): bool
{
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
        return false;
    }
    $host = strtolower((string) ($parts['host'] ?? ''));
    return in_array($host, [
        'github.com',
        'objects.githubusercontent.com',
        'release-assets.githubusercontent.com',
    ], true);
}

function update_http_get(string $url, int $maxBytes): string
{
    if (!updates_allowed_url($url)) {
        throw new RuntimeException('URL de actualización no permitida.');
    }
    $stream = fopen('php://temp', 'w+b');
    if ($stream === false) {
        throw new RuntimeException('No se pudo preparar la descarga.');
    }
    try {
        update_http_copy($url, $stream, $maxBytes, 20);
        rewind($stream);
        $data = stream_get_contents($stream);
        if ($data === false || $data === '') {
            throw new RuntimeException('La respuesta del servidor está vacía.');
        }
        return $data;
    } finally {
        fclose($stream);
    }
}

function update_http_download(string $url, string $target, int $maxBytes): void
{
    if (!updates_allowed_url($url)) {
        throw new RuntimeException('URL de actualización no permitida.');
    }
    $directory = dirname($target);
    if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('No se pudo crear la carpeta temporal.');
    }
    $handle = fopen($target, 'wb');
    if ($handle === false) {
        throw new RuntimeException('No se pudo escribir el paquete temporal.');
    }
    try {
        update_http_copy($url, $handle, $maxBytes, 120);
    } finally {
        fclose($handle);
    }
}

/**
 * Copia una URL HTTPS a un recurso local sin cargar el archivo completo en
 * memoria. Usa streams y recurre a cURL si allow_url_fopen está desactivado.
 */
function update_http_copy(string $url, $handle, int $maxBytes, int $timeout = 30): void
{
    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'follow_location' => 1,
            'max_redirects' => 5,
            'user_agent' => 'GandiaTech-CRM/' . APP_VERSION,
            'header' => "Accept: */*\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $source = @fopen($url, 'rb', false, $context);
    if ($source !== false) {
        $total = 0;
        while (!feof($source)) {
            $chunk = fread($source, 16384);
            if ($chunk === false) {
                break;
            }
            $total += strlen($chunk);
            if ($total > $maxBytes) {
                fclose($source);
                throw new RuntimeException('La respuesta supera el tamaño máximo permitido.');
            }
            if (fwrite($handle, $chunk) === false) {
                fclose($source);
                throw new RuntimeException('No se pudo escribir la respuesta.');
            }
        }
        fclose($source);
        if ($total === 0) {
            throw new RuntimeException('La respuesta del servidor está vacía.');
        }
        return;
    }

    if (!function_exists('curl_init')) {
        throw new RuntimeException('El servidor no puede realizar peticiones HTTPS.');
    }

    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('No se pudo iniciar la petición HTTPS.');
    }
    $total = 0;
    curl_setopt_array($curl, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_USERAGENT => 'GandiaTech-CRM/' . APP_VERSION,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use ($handle, $maxBytes, &$total): int {
            $total += strlen($chunk);
            if ($total > $maxBytes) {
                return 0;
            }
            $written = fwrite($handle, $chunk);
            return $written === false ? 0 : $written;
        },
    ]);
    $ok = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($ok === false || $status < 200 || $status >= 300) {
        throw new RuntimeException('La petición HTTPS falló' . ($error !== '' ? ': ' . $error : ' (' . $status . ').'));
    }
}

function updates_download(): never
{
    $manifest = updates_manifest(false)['manifest'];
    $tmpDir = BASE_PATH . '/data/tmp';
    updates_cleanup_temp($tmpDir);

    $target = $tmpDir . '/update-' . bin2hex(random_bytes(6)) . '.zip';
    try {
        update_http_download((string) $manifest['download_url'], $target, UPDATE_MAX_PACKAGE_BYTES);
        clearstatcache(true, $target);
        $size = (int) filesize($target);
        $sha256 = hash_file('sha256', $target);
        if ($size !== (int) $manifest['size']
            || !is_string($sha256)
            || !hash_equals((string) $manifest['sha256'], strtolower($sha256))) {
            throw new RuntimeException('El paquete descargado no coincide con el manifiesto firmado.');
        }
    } catch (Throwable $e) {
        @unlink($target);
        error_log('[CRM] Descarga de actualización: ' . $e->getMessage());
        throw new HttpError('No se pudo descargar el paquete de actualización.', 502);
    }

    $version = preg_replace('/[^0-9A-Za-z._-]/', '', (string) $manifest['version']) ?: 'nueva';
    header('Content-Type: application/zip');
    header('Content-Length: ' . (string) $size);
    header('Content-Disposition: attachment; filename="gandiatech-crm-basico-' . $version . '.zip"');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($target);
    @unlink($target);
    log_activity('settings', null, 'actualizacion_descargada', 'ha descargado la actualización ' . $manifest['version']);
    exit;
}

function updates_cleanup_temp(string $directory): void
{
    foreach (glob($directory . '/update-*.zip') ?: [] as $file) {
        if (@filemtime($file) < time() - 86400) {
            @unlink($file);
        }
    }
}
