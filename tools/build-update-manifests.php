<?php
declare(strict_types=1);

/**
 * Genera el inventario firmable y los deltas acumulados de una release.
 *
 * Uso:
 *   php tools/build-update-manifests.php \
 *     --version=1.3.0 --tag=v1.3.0 --repo=GandIATech/gandiatech-crm \
 *     --package=dist/gandiatech-crm-basico-1.3.0 \
 *     --zip=dist/gandiatech-crm-basico-1.3.0.zip \
 *     --out=dist --db-version=2 \
 *     --from=1.2.0:/tmp/prev/gandiatech-crm-basico-1.2.0
 *
 * El manifiesto latest.json resultante se firma después con la clave Ed25519.
 * Cada delta contiene delta.json y payload/<rutas>; su SHA-256 queda dentro del
 * manifiesto firmado, de modo que no hace falta una firma por delta.
 */

require_once __DIR__ . '/../src/UpdateInventory.php';

function fail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function arg(array $args, string $name, ?string $default = null): ?string
{
    foreach ($args as $arg) {
        if (str_starts_with($arg, '--' . $name . '=')) {
            return substr($arg, strlen($name) + 3);
        }
    }
    return $default;
}

/** @return list<string> */
function argAll(array $args, string $name): array
{
    $values = [];
    foreach ($args as $arg) {
        if (str_starts_with($arg, '--' . $name . '=')) {
            $values[] = substr($arg, strlen($name) + 3);
        }
    }
    return $values;
}

function rrmdir(string $dir): void
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

function zipDirectory(string $source, string $target): void
{
    if (is_file($target)) {
        @unlink($target);
    }
    // El ZIP se crea después de cambiar a la carpeta de origen; la ruta de
    // salida debe ser absoluta o se resolvería dentro de esa carpeta temporal.
    if (!str_starts_with($target, '/')) {
        $target = getcwd() . '/' . $target;
    }
    $command = 'cd ' . escapeshellarg($source) . ' && zip -qr ' . escapeshellarg($target) . ' .';
    $output = [];
    $status = 0;
    exec($command, $output, $status);
    if ($status !== 0 || !is_file($target)) {
        fail('No se pudo crear el ZIP delta: ' . implode("\n", $output));
    }
}

$args = array_slice($argv, 1);
$version = arg($args, 'version');
$tag = arg($args, 'tag', 'v' . $version);
$repo = arg($args, 'repo');
$package = arg($args, 'package');
$zip = arg($args, 'zip');
$out = arg($args, 'out', dirname((string) $zip));
$dbVersion = (int) arg($args, 'db-version', '1');
$minPhp = arg($args, 'min-php', '8.2');
$changelogUrl = arg($args, 'changelog-url');

if (!$version || !preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $version)) {
    fail('Falta --version con un número de versión válido.');
}
if (!$repo || preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo) !== 1) {
    fail('Falta --repo con el formato propietario/repositorio.');
}
if (!$package || !is_dir($package)) {
    fail('Falta --package o la carpeta no existe.');
}
if (!$zip || !is_file($zip)) {
    fail('Falta --zip o el ZIP completo no existe.');
}
if (!is_dir($out) && !mkdir($out, 0775, true) && !is_dir($out)) {
    fail('No se pudo crear la carpeta de salida.');
}

if (is_file($package . '/config/config.php')) {
    fail('El paquete contiene config/config.php. No se publica una release con configuración local.');
}
if (!is_file($package . '/src/bootstrap.php')) {
    fail('El paquete no contiene src/bootstrap.php.');
}

$files = UpdateInventory::scan($package);
$filesMap = UpdateInventory::map($files);
$fullSha = hash_file('sha256', $zip);
$fullSize = (int) filesize($zip);
if (!is_string($fullSha) || $fullSize <= 0) {
    fail('No se pudo calcular el checksum del ZIP completo.');
}

$deltas = [];
foreach (argAll($args, 'from') as $fromSpec) {
    $parts = explode(':', $fromSpec, 2);
    if (count($parts) !== 2 || !preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $parts[0]) || !is_dir($parts[1])) {
        fwrite(STDERR, "Aviso: se ignora --from inválido: " . $fromSpec . "\n");
        continue;
    }
    $fromVersion = $parts[0];
    $fromPackage = rtrim($parts[1], '/');
    if ($fromVersion === $version) {
        continue;
    }
    try {
        $oldFiles = UpdateInventory::scan($fromPackage);
    } catch (Throwable $e) {
        fwrite(STDERR, "Aviso: no se pudo inventariar $fromVersion: " . $e->getMessage() . "\n");
        continue;
    }

    $compare = UpdateInventory::compare($oldFiles, $files);
    $paths = array_values(array_unique(array_merge($compare['changed'], $compare['added'])));
    sort($paths, SORT_STRING);

    $deltaStage = $out . '/.delta-' . $fromVersion . '-to-' . $version;
    rrmdir($deltaStage);
    if (!mkdir($deltaStage . '/payload', 0775, true) && !is_dir($deltaStage . '/payload')) {
        fail('No se pudo preparar el delta ' . $fromVersion . '.');
    }

    $deltaFiles = [];
    foreach ($paths as $path) {
        $source = $package . '/' . $path;
        $target = $deltaStage . '/payload/' . $path;
        $parent = dirname($target);
        if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
            fail('No se pudo preparar ' . $path . ' en el delta.');
        }
        if (!copy($source, $target)) {
            fail('No se pudo copiar ' . $path . ' al delta.');
        }
        $deltaFiles[] = $filesMap[$path] + ['path' => $path];
    }

    $deltaJson = [
        'from' => $fromVersion,
        'to' => $version,
        'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'files' => $deltaFiles,
        'deleted' => $compare['deleted'],
    ];
    file_put_contents(
        $deltaStage . '/delta.json',
        json_encode($deltaJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );

    $deltaName = 'gandiatech-crm-delta-' . $fromVersion . '-to-' . $version . '.zip';
    $deltaZip = $out . '/' . $deltaName;
    zipDirectory($deltaStage, $deltaZip);
    rrmdir($deltaStage);

    $deltaSha = hash_file('sha256', $deltaZip);
    $deltaSize = (int) filesize($deltaZip);
    if (!is_string($deltaSha) || $deltaSize <= 0) {
        fail('No se pudo calcular el checksum del delta ' . $fromVersion . '.');
    }

    $deltas[$fromVersion] = [
        'url' => 'https://github.com/' . $repo . '/releases/download/' . $tag . '/' . $deltaName,
        'sha256' => $deltaSha,
        'size' => $deltaSize,
        'files' => count($deltaFiles),
        'deleted' => count($compare['deleted']),
    ];

    fwrite(STDOUT, sprintf(
        "Delta %s -> %s: %d ficheros, %d borrados, %d bytes\n",
        $fromVersion,
        $version,
        count($deltaFiles),
        count($compare['deleted']),
        $deltaSize
    ));
}

$manifest = [
    'version' => $version,
    'tag' => $tag,
    'min_php' => $minPhp,
    'db_version' => $dbVersion,
    'sha256' => $fullSha,
    'size' => $fullSize,
    'download_url' => 'https://github.com/' . $repo . '/releases/download/' . $tag . '/gandiatech-crm-basico-' . $version . '.zip',
    'changelog_url' => $changelogUrl ?: ('https://github.com/' . $repo . '/blob/' . $tag . '/CHANGELOG.md'),
    'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
    'files' => $files,
    'deltas' => $deltas,
];

$json = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($json)) {
    fail('No se pudo serializar el manifiesto.');
}
if (strlen($json) > 60000) {
    fail('El manifiesto supera los 60 KB. Habría que separar el inventario en files.json.');
}
file_put_contents($out . '/latest.json', $json . "\n");
fwrite(STDOUT, sprintf(
    "Manifiesto %s: %d ficheros, %d deltas, %d bytes\n",
    $version,
    count($files),
    count($deltas),
    strlen($json)
));
