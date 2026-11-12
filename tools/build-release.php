<?php
declare(strict_types=1);

/**
 * Build an installable WordPress ZIP from explicitly approved production paths.
 * Usage: php tools/build-release.php /path/to/plugin /path/to/plugin.zip [--production]
 *
 * This script is deliberately standalone: target PHP 8.1 through 8.5.
 * Never silently package hidden files, credentials, backups, or stale headers.
 */
function buildFail(string $message, int $code = 1): void
{
    fwrite(STDERR, $message."\n");
    exit($code);
}

/** @return list<string> */
function requiredHeaders(string $source, array $manifest): array
{
    $slug = $manifest['slug'] ?? null;
    if (!is_string($slug) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)
        || basename($source) !== $slug) {
        buildFail('Plugin folder must exactly match the manifest slug.');
    }
    $main = $source.'/'.$slug.'.php';
    if (!is_file($main) || is_link($main)) {
        buildFail('Missing or symlinked main plugin file.');
    }
    $text = file_get_contents($main);
    if ($text === false) {
        buildFail('Cannot read main plugin file.');
    }
    $header = substr($text, 0, 8192);
    foreach ([
        'Plugin Name' => 'name',
        'Version' => 'version',
        'Requires at least' => 'requires_wordpress',
        'Requires PHP' => 'requires_php',
        'Update URI' => 'update_uri',
    ] as $label => $key) {
        $pattern = '/^[ \t]*\*?[ \t]*'.preg_quote($label, '/').':[ \t]*([^\r\n]+)$/mi';
        if (!preg_match($pattern, $header, $matches) ||
            trim($matches[1]) !== ($manifest[$key] ?? null)) {
            buildFail("Plugin header '$label' does not match manifest '$key'.");
        }
    }
    $readme = $source.'/readme.txt';
    if (!is_file($readme) || is_link($readme)) {
        buildFail('Missing or symlinked readme.txt.');
    }
    $readmeText = file_get_contents($readme);
    if ($readmeText === false ||
        !preg_match('/^Stable tag:[ \t]*(\S+)[ \t]*$/mi', $readmeText, $tag) ||
        $tag[1] !== ($manifest['version'] ?? null)) {
        buildFail('Readme stable tag does not match manifest version.');
    }
    return [$slug, $main];
}

/**
 * Fail instead of accidentally publishing a secret or development artifact.
 * @param string $relative Forward-slash path relative to plugin root.
 */
function guardReleasePath(string $relative): void
{
    $segments = explode('/', str_replace('\\', '/', $relative));
    $deniedDirs = ['.git', '.github', 'node_modules', 'vendor', 'tests', 'test', 'coverage'];
    $deniedNames = ['id_rsa', 'id_ed25519', 'auth.json', 'composer.lock', 'package-lock.json',
        'yarn.lock', 'pnpm-lock.yaml', 'phpunit.xml', 'phpunit.xml.dist'];
    foreach ($segments as $segment) {
        $lower = strtolower($segment);
        if ($segment === '' || $segment === '.' || $segment === '..' || $segment[0] === '.' ||
            in_array($lower, $deniedDirs, true) || in_array($lower, $deniedNames, true)) {
            buildFail('Prohibited or hidden release path: '.$relative);
        }
    }
    $filename = strtolower(end($segments));
    if (preg_match('/\.(?:pem|key|p12|pfx|sqlite|sqlite3|db|sql|bak|backup|orig|swp|log|zip|tar|gz)$/D', $filename)) {
        buildFail('Potential secret/backup/archive in release: '.$relative);
    }
}

$source = isset($argv[1]) && is_string($argv[1]) ? realpath($argv[1]) : false;
$output = $argv[2] ?? null;
$production = in_array('--production', $argv, true);
if (!is_string($source) || !is_dir($source) ||
    !is_string($output) || $output === '' || !class_exists('ZipArchive')) {
    buildFail('Usage: php tools/build-release.php /path/to/plugin /path/to/plugin.zip [--production] (ext-zip required)', 2);
}
$source = rtrim($source, DIRECTORY_SEPARATOR);
$directory = dirname($output);
if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
    buildFail('Cannot create output directory.');
}
$outputDirectory = realpath($directory);
if ($outputDirectory === false) {
    buildFail('Cannot resolve output directory.');
}
$outputPath = $outputDirectory.DIRECTORY_SEPARATOR.basename($output);
if ($outputPath === $source || strpos($outputPath, $source.DIRECTORY_SEPARATOR) === 0) {
    buildFail('Release ZIP must be written outside the plugin source directory.');
}

$manifestPath = $source.'/mti-plugin.json';
if (!is_file($manifestPath) || is_link($manifestPath)) {
    buildFail('Missing or symlinked mti-plugin.json.');
}
require_once __DIR__.'/ManifestRules.php';
try {
    $manifest = ManifestRules::read($manifestPath);
} catch (InvalidArgumentException $e) {
    buildFail($e->getMessage());
}
$errors = ManifestRules::errors($manifest, $production);
if ($errors !== []) {
    buildFail("Invalid plugin manifest:\n".implode("\n", $errors));
}
[$slug] = requiredHeaders($source, $manifest);

$extras = $manifest['release_paths'] ?? [];
if (!is_array($extras) || array_values($extras) !== $extras || count($extras) > 40) {
    buildFail('Invalid release_paths list.');
}
foreach ($extras as $relative) {
    if (!is_string($relative) ||
        !preg_match('#^[A-Za-z0-9][A-Za-z0-9._-]*(?:/[A-Za-z0-9][A-Za-z0-9._-]*)*$#D', $relative)) {
        buildFail('Invalid or unsafe release_paths entry.');
    }
    guardReleasePath($relative);
    if (!file_exists($source.'/'.$relative) || is_link($source.'/'.$relative)) {
        buildFail('Missing or symlinked release path: '.$relative);
    }
}
$entrypoints = array_values(array_unique(array_merge([
    $slug.'.php', 'src', 'includes', 'assets', 'languages', 'templates',
    'uninstall.php', 'readme.txt', 'LICENSE', 'mti-plugin.json',
], $extras)));

/** @var list<string> $files */
$files = [];
/** @var list<string> $folders */
$folders = [$slug];
foreach ($entrypoints as $item) {
    $path = $source.'/'.$item;
    if (!file_exists($path)) {
        continue;
    }
    guardReleasePath($item);
    if (is_link($path)) {
        buildFail('Symlinks are not allowed in release packages: '.$item);
    }
    if (is_file($path)) {
        $files[] = $item;
        continue;
    }
    if (!is_dir($path)) {
        buildFail('Unrecognized release path: '.$item);
    }
    $folders[] = $slug.'/'.$item;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $file) {
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
        guardReleasePath($relative);
        if ($file->isLink()) {
            buildFail('Symlinks are not allowed in release packages: '.$relative);
        }
        if ($file->isDir()) {
            $folders[] = $slug.'/'.$relative;
        } elseif ($file->isFile()) {
            $files[] = $relative;
        }
    }
}
sort($folders, SORT_STRING);
sort($files, SORT_STRING);
$zip = new ZipArchive();
if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    buildFail('Unable to open release ZIP.');
}
foreach (array_unique($folders) as $folder) {
    if (!$zip->addEmptyDir($folder)) {
        $zip->close();
        @unlink($outputPath);
        buildFail('Unable to add release directory: '.$folder);
    }
}
foreach (array_unique($files) as $relative) {
    if (!$zip->addFile($source.'/'.$relative, $slug.'/'.$relative)) {
        $zip->close();
        @unlink($outputPath);
        buildFail('Unable to add release file: '.$relative);
    }
}
if (!$zip->close()) {
    @unlink($outputPath);
    buildFail('Unable to finalize release ZIP.');
}
$check = new ZipArchive();
if ($check->open($outputPath) !== true || $check->locateName($slug.'/'.$slug.'.php') === false) {
    if ($check->status === ZipArchive::ER_OK) {
        $check->close();
    }
    @unlink($outputPath);
    buildFail('Release ZIP validation failed.');
}
$check->close();
echo "Built $outputPath\n";
