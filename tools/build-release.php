<?php
declare(strict_types=1);

/**
 * Package only production files. Requires ext-zip.
 * Usage: php tools/build-release.php /path/to/plugin /path/to/release.zip
 */
$source = isset($argv[1]) ? realpath($argv[1]) : false;
$output = $argv[2] ?? '';
if (!$source || !is_dir($source) || $output === '' || !class_exists('ZipArchive')) {
    fwrite(STDERR, "Usage: php tools/build-release.php /path/to/plugin /path/to/release.zip (ext-zip required)\n");
    exit(2);
}
$manifestFile = $source . '/mti-plugin.json';
if (!is_file($manifestFile)) {
    fwrite(STDERR, "Missing mti-plugin.json\n");
    exit(1);
}
$manifest = json_decode((string) file_get_contents($manifestFile), true);
$slug = $manifest['slug'] ?? '';
if (!is_string($slug) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) ||
    !is_file($source . '/' . $slug . '.php') ||
    basename($source) !== $slug) {
    fwrite(STDERR, "Plugin directory, slug, or main PHP file does not match.\n");
    exit(1);
}
$entrypoints = [$slug . '.php', 'src', 'includes', 'assets', 'languages', 'templates', 'uninstall.php', 'readme.txt', 'LICENSE', 'mti-plugin.json'];
// Opt-in production assets for legacy plugins with root CSS, JS or templates.
$extra = $manifest['release_paths'] ?? [];
if (!is_array($extra) || !array_is_list($extra) || count($extra) > 40) {
    fwrite(STDERR, "Invalid release_paths list.\n");
    exit(1);
}
foreach ($extra as $relative) {
    if (!is_string($relative) ||
        !preg_match('#^[A-Za-z0-9][A-Za-z0-9._-]*(?:/[A-Za-z0-9][A-Za-z0-9._-]*)*$#D', $relative) ||
        in_array($relative, ['.git', '.github', 'node_modules', 'vendor', 'tests', 'test'], true)) {
        fwrite(STDERR, "Unsafe release path.\n");
        exit(1);
    }
    foreach (explode('/', $relative) as $segment) {
        if ($segment === '.' || $segment === '..' ||
            in_array($segment, ['.git', '.github', 'node_modules', 'vendor', 'tests', 'test'], true)) {
            fwrite(STDERR, "Prohibited release path.\n");
            exit(1);
        }
    }
    if (!file_exists($source . '/' . $relative) || is_link($source . '/' . $relative)) {
        fwrite(STDERR, "Missing or symlinked release path: $relative\n");
        exit(1);
    }
}
$entrypoints = array_values(array_unique(array_merge($entrypoints, $extra)));
$directory = dirname($output);
if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
    fwrite(STDERR, "Cannot create output directory.\n");
    exit(1);
}
$zip = new ZipArchive();
if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Unable to open release ZIP.\n");
    exit(1);
}
$zip->addEmptyDir($slug);
foreach ($entrypoints as $item) {
    $path = $source . '/' . $item;
    if (!file_exists($path)) {
        continue;
    }
    if (is_link($path)) {
        fwrite(STDERR, "Symlinks not allowed in release: $item\n");
        exit(1);
    }
    if (is_file($path)) {
        $zip->addFile($path, $slug . '/' . $item);
        continue;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $file) {
        if ($file->isLink()) {
            fwrite(STDERR, "Symlinks not allowed in release: {$file->getPathname()}\n");
            exit(1);
        }
        $relative = substr($file->getPathname(), strlen($source) + 1);
        if (preg_match('#(^|/)(\.git|\.github|node_modules|vendor|tests?)(/|$)#', $relative)) {
            continue;
        }
        if ($file->isDir()) {
            $zip->addEmptyDir($slug . '/' . $relative);
        } elseif ($file->isFile()) {
            $zip->addFile($file->getPathname(), $slug . '/' . $relative);
        }
    }
}
$zip->close();
$check = new ZipArchive();
if ($check->open($output) !== true || $check->locateName($slug . '/' . $slug . '.php') === false) {
    fwrite(STDERR, "Release ZIP validation failed.\n");
    exit(1);
}
$check->close();
echo "Built $output\n";
