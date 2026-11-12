<?php
declare(strict_types=1);

/**
 * Usage: php tools/validate-manifest.php /path/to/mti-plugin.json [--production]
 */
require_once __DIR__.'/ManifestRules.php';

$path = $argv[1] ?? null;
if (!is_string($path) || $path === '') {
    fwrite(STDERR, "Usage: php tools/validate-manifest.php /path/to/mti-plugin.json [--production]\n");
    exit(2);
}
try {
    $manifest = ManifestRules::read($path);
} catch (InvalidArgumentException $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
$errors = ManifestRules::errors($manifest, in_array('--production', $argv, true));
if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors)."\n");
    exit(1);
}
echo "Valid MTI manifest: {$manifest['slug']} {$manifest['version']}\n";
