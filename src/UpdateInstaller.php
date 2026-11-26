<?php
declare(strict_types=1);

require_once __DIR__ . '/UpdateInventory.php';

/**
 * Motor de actualización en un clic.
 *
 * Aplica únicamente los ficheros indicados por el manifiesto firmado, guarda
 * una copia de lo reemplazado, activa un modo mantenimiento breve y restaura
 * los ficheros si algo falla. Nunca toca config/ ni data/.
 */
final class UpdateInstaller
{
    public const MAINTENANCE_TTL = 900;
    public const MAX_ARTIFACT_BYTES = 50 * 1024 * 1024;
    public const MAX_UNCOMPRESSED_BYTES = 160 * 1024 * 1024;
    public const MAX_ARCHIVE_FILES = 6000;
    private const BACKUP_KEEP_DAYS = 30;
    private const STAGING_KEEP_SECONDS = 3600;

    private const UPDATABLE_DIRS = ['.', 'src', 'app', 'api', 'vendor', 'database'];

    public static function capabilities(): array
    {
        $reasons = [];
        $warnings = [];

        if (!class_exists('ZipArchive') && !class_exists('PharData')) {
            $reasons[] = 'El servidor no puede descomprimir archivos ZIP (faltan ZipArchive y Phar).';
        }

        foreach (array_unique(self::UPDATABLE_DIRS) as $dir) {
            $path = $dir === '.' ? BASE_PATH : BASE_PATH . '/' . $dir;
            if (!is_dir($path)) {
                if ($dir !== '.' && !is_writable(BASE_PATH)) {
                    $reasons[] = 'No se puede crear la carpeta ' . $dir . '/.';
                }
                continue;
            }
            if (!is_writable($path)) {
                $reasons[] = 'La carpeta ' . ($dir === '.' ? 'raíz' : $dir . '/') . ' no tiene permisos de escritura.';
            }
        }

        $free = @disk_free_space(BASE_PATH);
        if (is_float($free) || is_int($free)) {
            if ($free < 32 * 1024 * 1024) {
                $reasons[] = 'Queda muy poco espacio libre en el servidor para hacer la copia de seguridad.';
            }
        } else {
            $warnings[] = 'No se pudo comprobar el espacio libre del servidor.';
        }

        return [
            'ok' => $reasons === [],
            'reasons' => array_values(array_unique($reasons)),
            'warnings' => $warnings,
        ];
    }

    public static function deltaFor(array $manifest, string $from): ?array
    {
        $deltas = $manifest['deltas'] ?? null;
        if (!is_array($deltas) || !isset($deltas[$from]) || !is_array($deltas[$from])) {
            return null;
        }
        $entry = $deltas[$from];
        $url = (string) ($entry['url'] ?? '');
        $sha = strtolower((string) ($entry['sha256'] ?? ''));
        $size = (int) ($entry['size'] ?? 0);
        if ($url === '' || preg_match('/^[a-f0-9]{64}$/', $sha) !== 1 || $size <= 0 || $size > self::MAX_ARTIFACT_BYTES) {
            return null;
        }
        if (!self::isAllowedUrl($url)) {
            return null;
        }
        return [
            'from' => $from,
            'url' => $url,
            'sha256' => $sha,
            'size' => $size,
            'files' => isset($entry['files']) ? (int) $entry['files'] : null,
            'deleted' => isset($entry['deleted']) ? (int) $entry['deleted'] : null,
        ];
    }

    public static function installedSummary(): array
    {
        $inventory = self::readInstalledInventory();
        if ($inventory === null) {
            return ['version' => null, 'files' => 0, 'generated_at' => null];
        }
        return [
            'version' => $inventory['version'],
            'files' => count($inventory['files']),
            'generated_at' => $inventory['generated_at'],
        ];
    }

    public static function state(): ?array
    {
        $raw = @file_get_contents(self::stateFile());
        if ($raw === false || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    /**
     * @param array<string, mixed> $manifest Manifiesto ya verificado por firma.
     * @param array{force?: bool, artifact_path?: string, notify_email?: string} $options
     * @return array<string, mixed>
     */
    public static function apply(array $manifest, array $options = []): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $from = APP_VERSION;
        $to = (string) ($manifest['version'] ?? '');
        if (preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $to) !== 1) {
            throw new HttpError('La versión del manifiesto no es válida.', 422);
        }
        if (version_compare($to, $from, '<=')) {
            throw new HttpError('Ya tienes la última versión disponible (' . $from . ').', 409);
        }
        if (version_compare(PHP_VERSION, (string) ($manifest['min_php'] ?? '8.2'), '<')) {
            throw new HttpError('La nueva versión necesita PHP ' . (string) ($manifest['min_php'] ?? '8.2') . ' o superior.', 422);
        }

        $targetFiles = UpdateInventory::validateFileList($manifest['files'] ?? []);
        if ($targetFiles === []) {
            throw new HttpError('El manifiesto de esta versión no incluye el inventario de ficheros. Descarga el paquete manualmente.', 422);
        }
        $targetMap = UpdateInventory::map($targetFiles);

        $delta = self::deltaFor($manifest, $from);
        $artifact = $delta ?? [
            'from' => $from,
            'url' => (string) ($manifest['download_url'] ?? ''),
            'sha256' => strtolower((string) ($manifest['sha256'] ?? '')),
            'size' => (int) ($manifest['size'] ?? 0),
            'files' => count($targetFiles),
            'deleted' => null,
        ];
        if ($artifact['url'] === '' || !self::isAllowedUrl($artifact['url'])) {
            throw new HttpError('La URL del paquete de actualización no es válida.', 422);
        }
        if (preg_match('/^[a-f0-9]{64}$/', (string) $artifact['sha256']) !== 1
            || (int) $artifact['size'] <= 0
            || (int) $artifact['size'] > self::MAX_ARTIFACT_BYTES) {
            throw new HttpError('El paquete de actualización no tiene un tamaño o checksum válidos.', 422);
        }

        $capabilities = self::capabilities();
        if (!$capabilities['ok']) {
            throw new HttpError('Este servidor no puede aplicar la actualización automáticamente: ' . implode(' ', $capabilities['reasons']), 422);
        }

        $lock = self::acquireLock();
        $maintenanceStarted = false;
        $backupDir = null;
        $stagingDir = null;
        $artifactPath = null;

        try {
            self::cleanup();

            $artifactPath = self::obtainArtifact($artifact, $options);
            $stagingDir = self::stagingDir();
            self::extractArchive($artifactPath, $stagingDir, $delta !== null);

            $deltaFiles = [];
            $deleted = [];
            if ($delta !== null) {
                $deltaData = self::readDeltaManifest($stagingDir, $from, $to);
                $deltaFiles = UpdateInventory::validateFileList($deltaData['files'] ?? []);
                $deleted = UpdateInventory::validateDeletedList($deltaData['deleted'] ?? []);
                foreach ($deleted as $deletedPath) {
                    if (isset($targetMap[$deletedPath])) {
                        throw new RuntimeException('El delta intenta borrar un fichero que sigue existiendo: ' . $deletedPath);
                    }
                }
                $sourceRoot = $stagingDir . '/payload';
                if (!is_dir($sourceRoot)) {
                    throw new RuntimeException('El paquete delta no contiene la carpeta payload.');
                }
                $deltaMap = UpdateInventory::map($deltaFiles);
                foreach ($deltaMap as $path => $meta) {
                    if (!isset($targetMap[$path])) {
                        throw new RuntimeException('El delta incluye un fichero que no está en el manifiesto: ' . $path);
                    }
                    if ($targetMap[$path]['sha256'] !== $meta['sha256']) {
                        throw new RuntimeException('El hash de ' . $path . ' no coincide con el manifiesto.');
                    }
                }
                $missing = [];
                foreach ($targetMap as $path => $meta) {
                    if (!isset($deltaMap[$path]) && !is_file(BASE_PATH . '/' . $path)) {
                        $missing[] = $path;
                    }
                }
                if ($missing !== []) {
                    throw new HttpError(
                        'Faltan archivos de la versión instalada (' . implode(', ', array_slice($missing, 0, 5))
                        . '). Descarga el paquete completo para repararlos.',
                        409,
                        ['missing' => $missing]
                    );
                }
                $filesToCheck = $deltaFiles;
            } else {
                $sourceRoot = self::findPackageRoot($stagingDir);
                $filesToCheck = $targetFiles;
            }

            if ($deleted === [] && $delta === null) {
                $deleted = self::deletionsFromInstalled($targetMap);
            }

            foreach ($filesToCheck as $file) {
                $source = $sourceRoot . '/' . $file['path'];
                if (!is_file($source) || is_link($source)) {
                    throw new RuntimeException('Falta el fichero ' . $file['path'] . ' en el paquete.');
                }
                $size = filesize($source);
                $hash = hash_file('sha256', $source);
                if ($size !== $file['size'] || !is_string($hash) || !hash_equals($file['sha256'], $hash)) {
                    throw new RuntimeException('El fichero ' . $file['path'] . ' no coincide con el inventario firmado.');
                }
            }

            $installed = self::readInstalledInventory();
            $installedMap = $installed['files'] ?? [];
            $force = !empty($options['force']);
            $warnings = [];

            $writePaths = [];
            $conflicts = [];
            $preserved = [];
            $alreadyUpdated = 0;
            foreach ($filesToCheck as $file) {
                $path = $file['path'];
                $destination = BASE_PATH . '/' . $path;
                $localHash = is_file($destination) ? hash_file('sha256', $destination) : null;
                if (is_string($localHash) && hash_equals($file['sha256'], $localHash)) {
                    $alreadyUpdated++;
                    continue;
                }

                if ($path === '.htaccess' && $localHash !== null) {
                    $installedHash = $installedMap[$path]['sha256'] ?? null;
                    if ($installedHash === null || !hash_equals((string) $installedHash, $localHash)) {
                        $warnings[] = 'Se conserva tu .htaccess personalizado.';
                        $preserved[] = $path;
                        continue;
                    }
                }

                if (!$force && $installed !== null) {
                    if (isset($installedMap[$path])) {
                        if ($localHash !== null && !hash_equals((string) $installedMap[$path]['sha256'], $localHash)) {
                            $conflicts[] = $path;
                            continue;
                        }
                    } elseif ($localHash !== null) {
                        $conflicts[] = $path;
                        continue;
                    }
                }

                $writePaths[] = $file;
            }

            if ($conflicts !== [] && !$force) {
                $sample = array_slice($conflicts, 0, 8);
                $extra = count($conflicts) > count($sample) ? ' y ' . (count($conflicts) - count($sample)) . ' más' : '';
                throw new HttpError(
                    'La actualización se ha detenido porque hay archivos modificados en esta instalación: '
                    . implode(', ', $sample) . $extra
                    . '. Haz una copia de seguridad y vuelve a intentarlo marcando la opción de forzar.',
                    409,
                    ['conflicts' => $conflicts]
                );
            }

            $deletePaths = [];
            $skippedDeletes = [];
            foreach ($deleted as $path) {
                $destination = BASE_PATH . '/' . $path;
                if (!is_file($destination)) {
                    continue;
                }
                $localHash = hash_file('sha256', $destination);
                $installedHash = $installedMap[$path]['sha256'] ?? null;
                if (!$force && ($installedHash === null || !is_string($localHash) || !hash_equals((string) $installedHash, $localHash))) {
                    $skippedDeletes[] = $path;
                    $warnings[] = 'Se conserva ' . $path . ' porque no coincide con la versión instalada.';
                    continue;
                }
                $deletePaths[] = $path;
            }

            self::assertWritableTargets($writePaths, $deletePaths);

            self::enableMaintenance($to);
            $maintenanceStarted = true;

            $backupDir = self::backupDir($from, $to);
            $hadInstalledInventory = is_file(self::installedFile());
            if ($hadInstalledInventory) {
                @copy(self::installedFile(), $backupDir . '/installed-files.json');
            }
            $createdPlanned = [];
            foreach ($writePaths as $file) {
                if (!is_file(BASE_PATH . '/' . $file['path'])) {
                    $createdPlanned[] = $file['path'];
                }
            }
            $backupFiles = array_values(array_unique(array_merge(
                array_map(static fn (array $file): string => $file['path'], $writePaths),
                $deletePaths
            )));
            self::backupFiles($backupFiles, $backupDir);
            $dbBackup = self::backupDatabase($backupDir);
            self::writeBackupManifest($backupDir, $from, $to, $writePaths, $deletePaths, $createdPlanned, $dbBackup, $warnings);

            self::writeState([
                'status' => 'applying',
                'from' => $from,
                'to' => $to,
                'started_at' => now_utc(),
                'backup' => self::relativePath($backupDir),
                'created' => [],
            ]);

            $created = [];
            $written = 0;
            foreach ($writePaths as $file) {
                $source = $sourceRoot . '/' . $file['path'];
                $destination = BASE_PATH . '/' . $file['path'];
                if (!is_file($destination)) {
                    $created[] = $file['path'];
                }
                self::copyFile($source, $destination, $file['sha256']);
                $written++;
            }

            foreach ($deletePaths as $path) {
                $destination = BASE_PATH . '/' . $path;
                self::assertSafeDestination($destination);
                if (is_file($destination) && !@unlink($destination)) {
                    throw new RuntimeException('No se pudo eliminar ' . $path . '.');
                }
                self::removeEmptyParents($destination);
            }

            $state = self::state() ?? [];
            $state['created'] = $created;
            self::writeState($state);

            Database::migrateNow();

            $newVersion = self::readAppVersion(BASE_PATH . '/src/bootstrap.php');
            if ($newVersion !== $to) {
                throw new RuntimeException('La versión instalada (' . ($newVersion ?? 'desconocida') . ') no coincide con la esperada (' . $to . ').');
            }
            $expectedDb = (int) ($manifest['db_version'] ?? 0);
            if ($expectedDb > 0 && Database::schemaVersion() < $expectedDb) {
                throw new RuntimeException('La base de datos no se ha migrado a la versión de esquema ' . $expectedDb . '.');
            }
            $verifyMap = $delta !== null
                ? array_intersect_key($targetMap, array_fill_keys(array_map(static fn (array $file): string => $file['path'], $filesToCheck), true))
                : $targetMap;
            self::verifyTargetHashes($verifyMap, $preserved);

            self::writeInstalledInventory($targetMap, $to);
            self::writeState([
                'status' => 'done',
                'from' => $from,
                'to' => $to,
                'finished_at' => now_utc(),
                'backup' => self::relativePath($backupDir),
                'written' => $written,
                'deleted' => count($deletePaths),
            ]);
            self::disableMaintenance();
            $maintenanceStarted = false;
            self::clearPhpCaches();

            self::cleanupAfterSuccess($stagingDir, $artifactPath);
            @unlink(self::stateFile());

            return [
                'from' => $from,
                'to' => $to,
                'mode' => $delta !== null ? 'delta' : 'full',
                'written' => $written,
                'deleted' => count($deletePaths),
                'skipped' => count($skippedDeletes) + $alreadyUpdated,
                'warnings' => array_values(array_unique($warnings)),
                'backup' => self::relativePath($backupDir),
                'db_backup' => $dbBackup !== null ? self::relativePath($dbBackup) : null,
            ];
        } catch (Throwable $e) {
            if ($maintenanceStarted) {
                self::disableMaintenance();
            }
            if ($backupDir !== null && is_dir($backupDir)) {
                self::restoreBackup($backupDir);
            }
            self::writeState([
                'status' => 'failed',
                'from' => $from,
                'to' => $to,
                'failed_at' => now_utc(),
                'error' => $e->getMessage(),
                'backup' => $backupDir !== null ? self::relativePath($backupDir) : null,
            ]);
            self::clearPhpCaches();
            if ($stagingDir !== null) {
                self::removeTree($stagingDir);
            }
            if ($artifactPath !== null && is_file($artifactPath)) {
                @unlink($artifactPath);
            }
            if ($e instanceof HttpError) {
                throw $e;
            }
            error_log('[CRM] Actualización: ' . $e->getMessage());
            throw new HttpError('No se pudo completar la actualización. Se ha restaurado la versión anterior. Detalle: ' . $e->getMessage(), 500);
        } finally {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    private static function isAllowedUrl(string $url): bool
    {
        return function_exists('updates_allowed_url') && updates_allowed_url($url);
    }

    /** @return resource */
    private static function acquireLock()
    {
        $dir = self::updatesDir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new HttpError('No se pudo preparar la carpeta de actualizaciones.', 500);
        }
        $lock = @fopen($dir . '/update.lock', 'c');
        if ($lock === false) {
            throw new HttpError('No se pudo bloquear la actualización.', 500);
        }
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new HttpError('Ya hay una actualización en curso. Espera a que termine.', 409);
        }
        return $lock;
    }

    private static function obtainArtifact(array $artifact, array $options): string
    {
        $dir = self::updatesDir() . '/download';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear la carpeta de descargas.');
        }
        $target = $dir . '/update-' . bin2hex(random_bytes(6)) . '.zip';

        if (isset($options['artifact_path']) && PHP_SAPI === 'cli' && is_file((string) $options['artifact_path'])) {
            if (!@copy((string) $options['artifact_path'], $target)) {
                throw new RuntimeException('No se pudo preparar el paquete local.');
            }
        } else {
            update_http_download((string) $artifact['url'], $target, self::MAX_ARTIFACT_BYTES);
        }

        clearstatcache(true, $target);
        $size = (int) @filesize($target);
        $hash = hash_file('sha256', $target);
        if ($size !== (int) $artifact['size'] || !is_string($hash) || !hash_equals((string) $artifact['sha256'], strtolower($hash))) {
            @unlink($target);
            throw new RuntimeException('El paquete descargado no coincide con el manifiesto firmado.');
        }
        return $target;
    }

    private static function extractArchive(string $archive, string $target, bool $isDelta): void
    {
        if (!is_dir($target) && !@mkdir($target, 0775, true) && !is_dir($target)) {
            throw new RuntimeException('No se pudo crear la carpeta temporal de la actualización.');
        }

        $count = 0;
        $uncompressed = 0;

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($archive) !== true) {
                throw new RuntimeException('No se pudo abrir el paquete de actualización.');
            }
            try {
                for ($index = 0; $index < $zip->numFiles; $index++) {
                    $stat = $zip->statIndex($index);
                    if (!is_array($stat)) {
                        continue;
                    }
                    $name = (string) ($stat['name'] ?? '');
                    self::validateArchiveEntry($name, $isDelta);
                    if (str_ends_with($name, '/')) {
                        continue;
                    }
                    $count++;
                    $uncompressed += (int) ($stat['size'] ?? 0);
                    if ($count > self::MAX_ARCHIVE_FILES || $uncompressed > self::MAX_UNCOMPRESSED_BYTES) {
                        throw new RuntimeException('El paquete de actualización es demasiado grande.');
                    }
                    if (isset($stat['external_attributes'])) {
                        $mode = ((int) $stat['external_attributes'] >> 16) & 0xF000;
                        if ($mode === 0xA000) {
                            throw new RuntimeException('El paquete contiene enlaces simbólicos y no se puede aplicar.');
                        }
                    }
                }
                if (!$zip->extractTo($target)) {
                    throw new RuntimeException('No se pudo descomprimir el paquete de actualización.');
                }
            } finally {
                $zip->close();
            }
        } elseif (class_exists('PharData')) {
            $phar = new PharData($archive);
            $prefix = 'phar://' . $archive . '/';
            foreach (new RecursiveIteratorIterator($phar) as $entry) {
                if (!$entry instanceof SplFileInfo || $entry->isDir()) {
                    continue;
                }
                $name = str_starts_with($entry->getPathname(), $prefix)
                    ? substr($entry->getPathname(), strlen($prefix))
                    : $entry->getFilename();
                self::validateArchiveEntry($name, $isDelta);
                $count++;
                $uncompressed += (int) $entry->getSize();
                if ($count > self::MAX_ARCHIVE_FILES || $uncompressed > self::MAX_UNCOMPRESSED_BYTES) {
                    throw new RuntimeException('El paquete de actualización es demasiado grande.');
                }
            }
            $phar->extractTo($target, null, true);
        } else {
            throw new RuntimeException('El servidor no puede descomprimir el paquete.');
        }

        self::assertNoSymlinks($target);
        self::assertExtractedSize($target);
    }

    private static function assertExtractedSize(string $target): void
    {
        $count = 0;
        $total = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }
            $count++;
            $total += (int) $file->getSize();
            if ($count > self::MAX_ARCHIVE_FILES || $total > self::MAX_UNCOMPRESSED_BYTES) {
                throw new RuntimeException('El paquete descomprimido es demasiado grande.');
            }
        }
    }

    private static function validateArchiveEntry(string $name, bool $isDelta): void
    {
        $normalized = str_replace('\\', '/', $name);
        if ($normalized === '' || str_contains($normalized, "\0")
            || str_starts_with($normalized, '/')
            || preg_match('#(^|/)\.\.(/|$)#', $normalized) === 1) {
            throw new RuntimeException('El paquete contiene una ruta no segura.');
        }
        if ($isDelta) {
            if ($normalized !== 'delta.json' && !str_starts_with($normalized, 'payload/')) {
                throw new RuntimeException('El paquete delta contiene una ruta no esperada.');
            }
        }
    }

    private static function readDeltaManifest(string $staging, string $from, string $to): array
    {
        $raw = @file_get_contents($staging . '/delta.json');
        if ($raw === false) {
            throw new RuntimeException('El paquete delta no contiene delta.json.');
        }
        $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new RuntimeException('El delta no es válido.');
        }
        if ((string) ($data['from'] ?? '') !== $from || (string) ($data['to'] ?? '') !== $to) {
            throw new RuntimeException('El delta no corresponde a tu versión instalada.');
        }
        return $data;
    }

    private static function findPackageRoot(string $staging): string
    {
        if (is_file($staging . '/src/bootstrap.php')) {
            return $staging;
        }
        foreach (glob($staging . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (is_file($dir . '/src/bootstrap.php')) {
                return $dir;
            }
        }
        throw new RuntimeException('El paquete no contiene una instalación válida del CRM.');
    }

    /** @return array{version: string, generated_at: string, files: array<string, array{sha256: string, size: int}>}|null */
    private static function readInstalledInventory(): ?array
    {
        $raw = @file_get_contents(self::installedFile());
        if ($raw === false || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['files']) || !is_array($data['files'])) {
            return null;
        }
        $files = [];
        foreach ($data['files'] as $path => $meta) {
            $normalized = UpdateInventory::normalizePath((string) $path);
            if ($normalized === null || !UpdateInventory::isAllowedPath($normalized) || !is_array($meta)) {
                continue;
            }
            $sha = strtolower((string) ($meta['sha256'] ?? ''));
            if (preg_match('/^[a-f0-9]{64}$/', $sha) !== 1) {
                continue;
            }
            $files[$normalized] = ['sha256' => $sha, 'size' => (int) ($meta['size'] ?? 0)];
        }
        return [
            'version' => (string) ($data['version'] ?? ''),
            'generated_at' => (string) ($data['generated_at'] ?? ''),
            'files' => $files,
        ];
    }

    private static function writeInstalledInventory(array $targetMap, string $version): void
    {
        $files = [];
        foreach ($targetMap as $path => $meta) {
            if (!is_file(BASE_PATH . '/' . $path)) {
                continue;
            }
            // Se guarda el hash esperado de la release, no el real: así un
            // .htaccess personalizado se sigue detectando como modificación.
            $files[$path] = ['sha256' => $meta['sha256'], 'size' => (int) $meta['size']];
        }
        $payload = [
            'version' => $version,
            'generated_at' => now_utc(),
            'files' => $files,
        ];
        self::writeJson(self::installedFile(), $payload);
    }

    /**
     * @return list<string>
     */
    private static function deletionsFromInstalled(array $targetMap): array
    {
        $installed = self::readInstalledInventory();
        if ($installed === null) {
            return [];
        }
        $deleted = [];
        foreach ($installed['files'] as $path => $meta) {
            if (!isset($targetMap[$path]) && UpdateInventory::isAllowedPath($path)) {
                $deleted[] = $path;
            }
        }
        sort($deleted, SORT_STRING);
        return $deleted;
    }

    private static function assertWritableTargets(array $writePaths, array $deletePaths): void
    {
        $dirs = [BASE_PATH];
        foreach ($writePaths as $file) {
            $dirs[] = dirname(BASE_PATH . '/' . $file['path']);
        }
        foreach ($deletePaths as $path) {
            $dirs[] = dirname(BASE_PATH . '/' . $path);
        }
        foreach (array_unique($dirs) as $dir) {
            while ($dir !== '' && $dir !== '/' && !is_dir($dir)) {
                $parent = dirname($dir);
                if ($parent === $dir) {
                    break;
                }
                $dir = $parent;
            }
            if (is_dir($dir) && !is_writable($dir)) {
                throw new HttpError('No hay permisos para escribir en ' . self::relativePath($dir) . '.', 422);
            }
        }
    }

    private static function backupFiles(array $paths, string $backupDir): void
    {
        $filesDir = $backupDir . '/files';
        if (!is_dir($filesDir) && !@mkdir($filesDir, 0775, true) && !is_dir($filesDir)) {
            throw new RuntimeException('No se pudo crear la copia de seguridad de los ficheros.');
        }
        foreach ($paths as $path) {
            $source = BASE_PATH . '/' . $path;
            if (!is_file($source)) {
                continue;
            }
            $destination = $filesDir . '/' . $path;
            $parent = dirname($destination);
            if (!is_dir($parent) && !@mkdir($parent, 0775, true) && !is_dir($parent)) {
                throw new RuntimeException('No se pudo preparar la copia de seguridad de ' . $path . '.');
            }
            if (!@copy($source, $destination)) {
                throw new RuntimeException('No se pudo guardar la copia de seguridad de ' . $path . '.');
            }
        }
    }

    private static function backupDatabase(string $backupDir): ?string
    {
        try {
            return Database::backupToFile($backupDir . '/database-backup');
        } catch (Throwable $e) {
            throw new RuntimeException('No se pudo hacer la copia de seguridad de la base de datos: ' . $e->getMessage(), 0, $e);
        }
    }

    private static function writeBackupManifest(
        string $backupDir,
        string $from,
        string $to,
        array $writePaths,
        array $deletePaths,
        array $createdPlanned,
        ?string $dbBackup,
        array $warnings
    ): void {
        self::writeJson($backupDir . '/backup.json', [
            'from' => $from,
            'to' => $to,
            'created_at' => now_utc(),
            'files' => array_map(static fn (array $file): string => $file['path'], $writePaths),
            'deleted' => $deletePaths,
            'created' => $createdPlanned,
            'db_backup' => $dbBackup !== null ? basename($dbBackup) : null,
            'warnings' => array_values(array_unique($warnings)),
        ]);
    }

    private static function restoreBackup(string $backupDir): void
    {
        $raw = @file_get_contents($backupDir . '/backup.json');
        $manifest = $raw !== false ? json_decode($raw, true) : null;
        if (!is_array($manifest)) {
            return;
        }
        if (is_file($backupDir . '/installed-files.json')) {
            @copy($backupDir . '/installed-files.json', self::installedFile());
        } elseif (!is_file($backupDir . '/installed-files.json')) {
            @unlink(self::installedFile());
        }
        $state = self::state() ?? [];
        $created = array_unique(array_merge(
            (array) ($manifest['created'] ?? []),
            (array) ($state['created'] ?? [])
        ));
        foreach ($created as $path) {
            $normalized = UpdateInventory::normalizePath((string) $path);
            if ($normalized !== null) {
                @unlink(BASE_PATH . '/' . $normalized);
            }
        }
        foreach ((array) ($manifest['files'] ?? []) as $path) {
            $normalized = UpdateInventory::normalizePath((string) $path);
            if ($normalized === null) {
                continue;
            }
            $source = $backupDir . '/files/' . $normalized;
            if (is_file($source)) {
                $destination = BASE_PATH . '/' . $normalized;
                $parent = dirname($destination);
                if (!is_dir($parent)) {
                    @mkdir($parent, 0775, true);
                }
                @copy($source, $destination);
            }
        }
    }

    private static function verifyTargetHashes(array $targetMap, array $preserved = []): void
    {
        $skip = array_fill_keys($preserved, true);
        foreach ($targetMap as $path => $meta) {
            if (isset($skip[$path])) {
                continue;
            }
            $full = BASE_PATH . '/' . $path;
            if (!is_file($full)) {
                throw new RuntimeException('Falta el fichero ' . $path . ' después de actualizar.');
            }
            $hash = hash_file('sha256', $full);
            if (!is_string($hash) || !hash_equals((string) $meta['sha256'], $hash)) {
                throw new RuntimeException('El fichero ' . $path . ' no se copió correctamente.');
            }
        }
    }

    private static function copyFile(string $source, string $destination, string $expectedHash): void
    {
        self::assertSafeDestination($destination);
        $parent = dirname($destination);
        if (!is_dir($parent) && !@mkdir($parent, 0775, true) && !is_dir($parent)) {
            throw new RuntimeException('No se pudo crear la carpeta ' . self::relativePath($parent) . '.');
        }
        self::assertNotSymlink($destination);
        $temp = $destination . '.update-' . bin2hex(random_bytes(4));
        if (!@copy($source, $temp)) {
            throw new RuntimeException('No se pudo copiar ' . self::relativePath($destination) . '.');
        }
        @chmod($temp, 0644);
        $hash = hash_file('sha256', $temp);
        if (!is_string($hash) || !hash_equals($expectedHash, $hash)) {
            @unlink($temp);
            throw new RuntimeException('La copia de ' . self::relativePath($destination) . ' no es válida.');
        }
        if (!@rename($temp, $destination)) {
            @unlink($temp);
            throw new RuntimeException('No se pudo reemplazar ' . self::relativePath($destination) . '.');
        }
    }

    private static function assertNotSymlink(string $path): void
    {
        if (is_link($path)) {
            throw new RuntimeException('La ruta ' . self::relativePath($path) . ' es un enlace simbólico.');
        }
    }

    private static function assertSafeDestination(string $destination): void
    {
        if (!str_starts_with($destination, BASE_PATH . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('La ruta de destino no pertenece al CRM.');
        }
        $relative = substr($destination, strlen(BASE_PATH) + 1);
        $current = BASE_PATH;
        foreach (explode('/', str_replace('\\', '/', $relative)) as $part) {
            $current .= DIRECTORY_SEPARATOR . $part;
            if (is_link($current)) {
                throw new RuntimeException('La ruta ' . self::relativePath($current) . ' es un enlace simbólico.');
            }
            if (!file_exists($current)) {
                break;
            }
        }
    }

    private static function assertNoSymlinks(string $root): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isLink()) {
                throw new RuntimeException('El paquete contiene enlaces simbólicos y no se puede aplicar.');
            }
        }
    }

    private static function readAppVersion(string $bootstrap): ?string
    {
        $content = @file_get_contents($bootstrap);
        if ($content === false) {
            return null;
        }
        return preg_match("/define\\('APP_VERSION',\\s*'([^']+)'\\);/", $content, $matches) === 1 ? $matches[1] : null;
    }

    private static function clearPhpCaches(): void
    {
        clearstatcache(true);
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    private static function enableMaintenance(string $toVersion): void
    {
        self::writeJson(self::maintenanceFile(), [
            'started_at' => time(),
            'version' => $toVersion,
            'token' => bin2hex(random_bytes(12)),
        ]);
    }

    private static function disableMaintenance(): void
    {
        @unlink(self::maintenanceFile());
    }

    private static function writeState(array $state): void
    {
        self::writeJson(self::stateFile(), $state);
    }

    private static function writeJson(string $file, array $data): void
    {
        $parent = dirname($file);
        if (!is_dir($parent) && !@mkdir($parent, 0775, true) && !is_dir($parent)) {
            throw new RuntimeException('No se pudo crear ' . self::relativePath($parent) . '.');
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || @file_put_contents($file, $json, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo escribir ' . self::relativePath($file) . '.');
        }
    }

    private static function cleanupAfterSuccess(?string $staging, ?string $artifact): void
    {
        if ($staging !== null) {
            self::removeTree($staging);
        }
        if ($artifact !== null && is_file($artifact)) {
            @unlink($artifact);
        }
    }

    private static function cleanup(): void
    {
        $updates = self::updatesDir();
        foreach (glob($updates . '/download/*.zip') ?: [] as $file) {
            if (@filemtime($file) < time() - self::STAGING_KEEP_SECONDS) {
                @unlink($file);
            }
        }
        foreach (glob($updates . '/staging/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (@filemtime($dir) < time() - self::STAGING_KEEP_SECONDS) {
                self::removeTree($dir);
            }
        }
        foreach (glob($updates . '/backup/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (@filemtime($dir) < time() - self::BACKUP_KEEP_DAYS * 86400) {
                self::removeTree($dir);
            }
        }
    }

    private static function removeEmptyParents(string $file): void
    {
        $dir = dirname($file);
        while ($dir !== BASE_PATH && str_starts_with($dir, BASE_PATH . DIRECTORY_SEPARATOR)) {
            $entries = @scandir($dir);
            if ($entries === false || count(array_diff($entries, ['.', '..'])) > 0) {
                break;
            }
            @rmdir($dir);
            $dir = dirname($dir);
        }
    }

    private static function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isDir() && !$file->isLink()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($dir);
    }

    private static function relativePath(string $path): string
    {
        $prefix = rtrim(BASE_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
    }

    private static function updatesDir(): string
    {
        return BASE_PATH . '/data/updates';
    }

    private static function stagingDir(): string
    {
        $dir = self::updatesDir() . '/staging/' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
        if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo preparar la carpeta temporal.');
        }
        return $dir;
    }

    private static function backupDir(string $from, string $to): string
    {
        $dir = self::updatesDir() . '/backup/' . gmdate('Ymd-His') . '-' . preg_replace('/[^0-9A-Za-z._-]/', '', $from . '-to-' . $to);
        if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear la carpeta de copia de seguridad.');
        }
        return $dir;
    }

    private static function installedFile(): string
    {
        return BASE_PATH . '/data/installed-files.json';
    }

    private static function stateFile(): string
    {
        return self::updatesDir() . '/state.json';
    }

    private static function maintenanceFile(): string
    {
        return BASE_PATH . '/data/maintenance.json';
    }
}
