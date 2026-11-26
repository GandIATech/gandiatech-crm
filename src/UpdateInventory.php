<?php
declare(strict_types=1);

/**
 * Inventario de ficheros de una release.
 *
 * Se usa en dos sitios:
 *  - tools/build-update-manifests.php, para generar el inventario firmado y los
 *    deltas acumulados durante la publicación.
 *  - UpdateInstaller, para comparar la instalación local con el paquete.
 */
final class UpdateInventory
{
    /** Carpetas que nunca forman parte de una actualización automática. */
    public const EXCLUDED_TOP_LEVEL = ['config', 'data', 'install', '.git', 'dist', 'node_modules'];

    /** Ficheros de sistema que no deben viajar en un paquete. */
    private const EXCLUDED_NAMES = ['.DS_Store', 'Thumbs.db', 'desktop.ini'];

    public const MAX_FILES = 6000;

    /**
     * Normaliza una ruta relativa y comprueba que sea segura.
     */
    public static function normalizePath(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');
        if ($path === '' || str_contains($path, "\0")) {
            return null;
        }
        if (preg_match('#(^|/)\.\.(/|$)#', $path) === 1) {
            return null;
        }
        if (preg_match('#^[A-Za-z]:#', $path) === 1) {
            return null;
        }
        $parts = array_values(array_filter(explode('/', $path), static fn (string $part): bool => $part !== '' && $part !== '.'));
        if ($parts === []) {
            return null;
        }
        return implode('/', $parts);
    }

    /**
     * Indica si una ruta puede actualizarse automáticamente.
     */
    public static function isAllowedPath(string $path): bool
    {
        $normalized = self::normalizePath($path);
        if ($normalized === null) {
            return false;
        }
        $top = explode('/', $normalized)[0];
        return !in_array($top, self::EXCLUDED_TOP_LEVEL, true);
    }

    /**
     * Recorre un árbol empaquetado y devuelve una lista ordenada de ficheros
     * con su hash y tamaño. No sigue enlaces simbólicos.
     *
     * @return list<array{path: string, sha256: string, size: int}>
     */
    public static function scan(string $root): array
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        if (!is_dir($root)) {
            throw new RuntimeException('No existe la carpeta que se quiere inventariar: ' . $root);
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile() || $file->isLink()) {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($root) + 1);
            $relative = str_replace('\\', '/', $relative);
            if (!self::isAllowedPath($relative)) {
                continue;
            }
            if (in_array(basename($relative), self::EXCLUDED_NAMES, true)) {
                continue;
            }
            $hash = hash_file('sha256', $file->getPathname());
            if (!is_string($hash)) {
                throw new RuntimeException('No se pudo calcular el hash de ' . $relative . '.');
            }
            $files[] = [
                'path' => $relative,
                'sha256' => $hash,
                'size' => (int) $file->getSize(),
            ];
            if (count($files) > self::MAX_FILES) {
                throw new RuntimeException('El paquete tiene demasiados ficheros para el inventario.');
            }
        }

        usort($files, static fn (array $a, array $b): int => strcmp($a['path'], $b['path']));
        return $files;
    }

    /**
     * @param list<array{path: string, sha256: string, size: int}> $files
     * @return array<string, array{sha256: string, size: int}>
     */
    public static function map(array $files): array
    {
        $map = [];
        foreach ($files as $file) {
            $map[$file['path']] = ['sha256' => $file['sha256'], 'size' => (int) $file['size']];
        }
        return $map;
    }

    /**
     * Compara dos inventarios y devuelve las rutas que hay que aplicar.
     *
     * @param list<array{path: string, sha256: string, size: int}> $old
     * @param list<array{path: string, sha256: string, size: int}> $new
     * @return array{changed: list<string>, added: list<string>, deleted: list<string>}
     */
    public static function compare(array $old, array $new): array
    {
        $oldMap = self::map($old);
        $newMap = self::map($new);

        $changed = [];
        $added = [];
        foreach ($newMap as $path => $meta) {
            if (!isset($oldMap[$path])) {
                $added[] = $path;
            } elseif ($oldMap[$path]['sha256'] !== $meta['sha256']) {
                $changed[] = $path;
            }
        }

        $deleted = [];
        foreach ($oldMap as $path => $meta) {
            if (!isset($newMap[$path])) {
                $deleted[] = $path;
            }
        }

        sort($changed, SORT_STRING);
        sort($added, SORT_STRING);
        sort($deleted, SORT_STRING);

        return ['changed' => $changed, 'added' => $added, 'deleted' => $deleted];
    }

    /**
     * Valida una lista de ficheros recibida desde un manifiesto.
     *
     * @param mixed $raw
     * @return list<array{path: string, sha256: string, size: int}>
     */
    public static function validateFileList(mixed $raw): array
    {
        if (!is_array($raw)) {
            throw new RuntimeException('El inventario del manifiesto no es válido.');
        }
        $files = [];
        $seen = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                throw new RuntimeException('Una entrada del inventario no es válida.');
            }
            $path = self::normalizePath((string) ($entry['path'] ?? ''));
            $hash = strtolower((string) ($entry['sha256'] ?? ''));
            $size = (int) ($entry['size'] ?? -1);
            if ($path === null || !self::isAllowedPath($path)) {
                throw new RuntimeException('El inventario contiene una ruta no permitida: ' . (string) ($entry['path'] ?? ''));
            }
            if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
                throw new RuntimeException('El hash de ' . $path . ' no es válido.');
            }
            if ($size < 0) {
                throw new RuntimeException('El tamaño de ' . $path . ' no es válido.');
            }
            if (isset($seen[$path])) {
                throw new RuntimeException('El inventario contiene una ruta duplicada: ' . $path);
            }
            $seen[$path] = true;
            $files[] = ['path' => $path, 'sha256' => $hash, 'size' => $size];
            if (count($files) > self::MAX_FILES) {
                throw new RuntimeException('El inventario tiene demasiados ficheros.');
            }
        }
        return $files;
    }

    /**
     * Valida una lista de rutas borradas.
     *
     * @param mixed $raw
     * @return list<string>
     */
    public static function validateDeletedList(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $deleted = [];
        foreach ($raw as $path) {
            $normalized = self::normalizePath((string) $path);
            if ($normalized === null || !self::isAllowedPath($normalized)) {
                throw new RuntimeException('El manifiesto contiene un borrado no permitido: ' . (string) $path);
            }
            if (basename($normalized) === '.htaccess') {
                continue;
            }
            $deleted[] = $normalized;
        }
        $deleted = array_values(array_unique($deleted));
        sort($deleted, SORT_STRING);
        return $deleted;
    }
}
