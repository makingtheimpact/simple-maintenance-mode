<?php
declare(strict_types=1);

/**
 * Development/build-time validation, shared by manifest CLI and ZIP builder.
 * Zero third-party dependencies; PHP 8.1 to 8.5.
 */
final class ManifestRules
{
    /** @return array<string,mixed> */
    public static function read(string $path): array
    {
        if (!is_file($path) || is_link($path)) {
            throw new InvalidArgumentException('Missing or symlinked mti-plugin.json.');
        }
        $bytes = file_get_contents($path);
        if (!is_string($bytes) || strlen($bytes) > 65536) {
            throw new InvalidArgumentException('Cannot read manifest or manifest too large.');
        }
        try {
            // Decode as object first to reject a JSON list pretending to be a manifest.
            $object = json_decode($bytes, false, 32, JSON_THROW_ON_ERROR);
            $data = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Invalid JSON manifest.', 0, $e);
        }
        if (!$object instanceof stdClass || !is_array($data)) {
            throw new InvalidArgumentException('Manifest root must be a JSON object.');
        }
        return $data;
    }

    /** @param array<string,mixed> $data @return list<string> */
    public static function errors(array $data, bool $production = false): array
    {
        $errors = [];
        $required = [
            'name','slug','version','namespace','license','requires_wordpress',
            'requires_php','update_uri','update_endpoints','package_hosts','public_keys',
        ];
        foreach ($required as $key) {
            if (!array_key_exists($key, $data)) {
                $errors[] = 'Missing '.$key;
            }
        }
        foreach (array_keys($data) as $key) {
            if (!in_array($key, array_merge($required, ['release_paths']), true)) {
                $errors[] = 'Unknown field '.$key;
            }
        }
        foreach (['name', 'license'] as $key) {
            $value = $data[$key] ?? null;
            if (!is_string($value) || strlen(trim($value)) < 3 || strlen($value) > 200 ||
                preg_match('/[\x00-\x1F\x7F]/', $value)) {
                $errors[] = 'Invalid '.$key;
            }
        }
        foreach ([
            'slug' => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',
            'version' => '/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9.-]+)?$/D',
            'namespace' => '/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)+$/D',
            'requires_wordpress' => '/^[0-9]+\.[0-9]+(?:\.[0-9]+)?$/D',
            'requires_php' => '/^[0-9]+\.[0-9]+(?:\.[0-9]+)?$/D',
        ] as $key => $pattern) {
            if (!is_string($data[$key] ?? null) || !preg_match($pattern, $data[$key])) {
                $errors[] = 'Invalid '.$key;
            }
        }
        if (is_string($data['requires_php'] ?? null) &&
            version_compare($data['requires_php'], '8.1', '<')) {
            $errors[] = 'The framework runtime requires PHP 8.1 or newer';
        }
        if (!self::safeHttps($data['update_uri'] ?? null)) {
            $errors[] = 'Invalid HTTPS update_uri';
        }
        $endpoints = $data['update_endpoints'] ?? null;
        if (!is_array($endpoints) || array_values($endpoints) !== $endpoints ||
            count($endpoints) < 2 || count($endpoints) > 3) {
            $errors[] = 'Expected 2–3 update endpoints';
        } else {
            $unique = [];
            foreach ($endpoints as $endpoint) {
                if (!self::safeHttps($endpoint)) {
                    $errors[] = 'Invalid HTTPS update endpoint';
                    continue;
                }
                $unique[] = $endpoint;
            }
            if (count(array_unique($unique)) !== count($endpoints)) {
                $errors[] = 'Duplicate update endpoints';
            }
        }
        $hosts = $data['package_hosts'] ?? null;
        if (!is_array($hosts) || array_values($hosts) !== $hosts || $hosts === []) {
            $errors[] = 'Invalid package_hosts list';
        } else {
            $unique = [];
            foreach ($hosts as $host) {
                if (!is_string($host) || !self::validHostname($host)) {
                    $errors[] = 'Invalid package host';
                    continue;
                }
                $unique[] = strtolower($host);
            }
            if (count(array_unique($unique)) !== count($hosts)) {
                $errors[] = 'Duplicate package hosts';
            }
        }
        $keys = $data['public_keys'] ?? null;
        if (!is_array($keys) || count($keys) > 8 ||
            ($keys !== [] && array_values($keys) === $keys)) {
            $errors[] = 'Invalid public_keys object';
        } else {
            foreach ($keys as $id => $key) {
                if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{1,40}$/D', $id) ||
                    !is_string($key) ||
                    strlen((string) base64_decode($key, true)) !== 32) {
                    $errors[] = 'Invalid Ed25519 public key';
                }
            }
            if ($production && $keys === []) {
                $errors[] = 'Production release requires pinned Ed25519 public keys';
            }
        }
        $paths = $data['release_paths'] ?? [];
        if (!is_array($paths) || array_values($paths) !== $paths ||
            count($paths) > 40) {
            $errors[] = 'Invalid release_paths list';
        } else {
            $unique = [];
            foreach ($paths as $relative) {
                if (!is_string($relative) ||
                    !preg_match('#^[A-Za-z0-9][A-Za-z0-9._-]*(?:/[A-Za-z0-9][A-Za-z0-9._-]*)*$#D', $relative)) {
                    $errors[] = 'Unsafe release path';
                    continue;
                }
                $unique[] = $relative;
                foreach (explode('/', $relative) as $segment) {
                    if ($segment[0] === '.' || in_array(strtolower($segment),
                        ['.git', '.github', 'node_modules', 'vendor', 'test', 'tests', 'coverage'], true)) {
                        $errors[] = 'Prohibited release path';
                    }
                }
            }
            if (count(array_unique($unique)) !== count($paths)) {
                $errors[] = 'Duplicate release paths';
            }
        }
        return $errors;
    }

    private static function validHostname(string $host): bool
    {
        return strlen($host) <= 253 &&
            preg_match('/^(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}$/D', $host) === 1;
    }

    /** @param mixed $candidate */
    private static function safeHttps($candidate): bool
    {
        if (!is_string($candidate) || filter_var($candidate, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $url = parse_url($candidate);
        if (!is_array($url) || strtolower((string) ($url['scheme'] ?? '')) !== 'https' ||
            isset($url['user']) || isset($url['pass']) || isset($url['port'])) {
            return false;
        }
        return self::validHostname((string) ($url['host'] ?? ''));
    }
}
