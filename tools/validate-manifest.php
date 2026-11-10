<?php
declare(strict_types=1);

/**
 * Zero-dependency manifest validation (mirrors the bundled JSON Schema).
 * Usage: php tools/validate-manifest.php /path/to/mti-plugin.json
 */
$path = $argv[1] ?? '';
if ($path === '' || !is_file($path)) {
    fwrite(STDERR, "Usage: php tools/validate-manifest.php /path/to/mti-plugin.json\n");
    exit(2);
}
try {
    $data = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    fwrite(STDERR, "Invalid JSON: " . $e->getMessage() . "\n");
    exit(1);
}
if (!is_array($data)) {
    fwrite(STDERR, "Manifest must be an object.\n");
    exit(1);
}
$required = ['name','slug','version','namespace','license','requires_wordpress','requires_php','update_uri','update_endpoint','package_hosts'];
$errors = [];
foreach ($required as $key) {
    if (!array_key_exists($key, $data)) {
        $errors[] = "Missing $key";
    }
}
$patterns = [
    'slug' => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',
    'version' => '/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/D',
    'namespace' => '/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)+$/D',
    'requires_wordpress' => '/^\d+\.\d+(?:\.\d+)?$/D',
    'requires_php' => '/^\d+\.\d+(?:\.\d+)?$/D',
];
foreach ($patterns as $key => $pattern) {
    if (isset($data[$key]) && (!is_string($data[$key]) || !preg_match($pattern, $data[$key]))) {
        $errors[] = "Invalid $key";
    }
}
foreach (['name', 'license'] as $key) {
    if (isset($data[$key]) && (!is_string($data[$key]) || strlen($data[$key]) < 3)) {
        $errors[] = "Invalid $key";
    }
}
foreach (['update_uri','update_endpoint'] as $key) {
    $url = $data[$key] ?? null;
    if (!is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false || parse_url($url, PHP_URL_SCHEME) !== 'https') {
        $errors[] = "Invalid HTTPS $key";
    }
}
$hosts = $data['package_hosts'] ?? null;
if (!is_array($hosts) || array_is_list($hosts) === false || $hosts === [] ||
    count(array_unique($hosts)) !== count($hosts)) {
    $errors[] = 'Invalid package_hosts';
} else {
    foreach ($hosts as $host) {
        if (!is_string($host) || !preg_match('/^[A-Za-z0-9.-]+$/D', $host)) {
            $errors[] = 'Invalid package host';
        }
    }
}
$allowedOptional = ['release_paths'];
$paths = $data['release_paths'] ?? [];
if (!is_array($paths) || !array_is_list($paths) || count($paths) > 40 ||
    count(array_unique($paths, SORT_REGULAR)) !== count($paths)) {
    $errors[] = 'Invalid release_paths';
} else {
    foreach ($paths as $relative) {
        if (!is_string($relative) ||
            !preg_match('#^[A-Za-z0-9][A-Za-z0-9._-]*(?:/[A-Za-z0-9][A-Za-z0-9._-]*)*$#D', $relative)) {
            $errors[] = 'Unsafe release path';
            continue;
        }
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '.' || $segment === '..' ||
                in_array($segment, ['.git','.github','node_modules','vendor','tests','test'], true)) {
                $errors[] = 'Prohibited release path';
            }
        }
    }
}
$unknown = array_diff(array_keys($data), array_merge($required, $allowedOptional));
foreach ($unknown as $key) {
    $errors[] = "Unknown field $key";
}
if ($errors) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}
echo "Valid MTI manifest: {$data['slug']} {$data['version']}\n";
