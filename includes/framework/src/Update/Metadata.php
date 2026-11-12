<?php
declare(strict_types=1);

namespace MTI\Plugins\SimpleMaintenanceMode\Framework\Update;

/**
 * Validates SIGNED update payload fields. Call only after SignedEnvelope::verify().
 */
final class Metadata
{
    /**
     * @param mixed $raw Decoded, signature-verified payload.
     * @param list<string> $allowedHosts Exact approved ZIP-download hosts.
     * @return array<string, mixed>|null
     */
    public static function normalize($raw, string $slug, string $updateUri, array $allowedHosts): ?array
    {
        if (!is_array($raw) || ($raw['slug'] ?? null) !== $slug || ($raw['update_uri'] ?? null) !== $updateUri) {
            return null;
        }
        $version = $raw['version'] ?? null;
        $sha = $raw['sha256'] ?? null;
        $expires = $raw['expires'] ?? null;
        if (!is_string($version) ||
            !preg_match('/^\d+\.\d+\.\d+(?:[-+][a-zA-Z0-9.-]+)?$/D', $version) ||
            !is_string($sha) || !preg_match('/^[a-f0-9]{64}$/D', $sha) ||
            !is_int($expires) || $expires < time() - 300 ||
            $expires > time() + 90 * 86400) {
            return null;
        }
        $package = $raw['package'] ?? null;
        if (!is_string($package) || !self::isAllowedUrl($package, $allowedHosts)) {
            return null;
        }
        $mirrors = $raw['mirrors'] ?? [];
        if (!is_array($mirrors) || count($mirrors) > 4 || array_values($mirrors) !== $mirrors) {
            return null;
        }
        foreach ($mirrors as $mirror) {
            if (!is_string($mirror) || !self::isAllowedUrl($mirror, $allowedHosts)) {
                return null;
            }
        }
        $homepage = $raw['homepage'] ?? '';
        if (!is_string($homepage) || ($homepage !== '' && !self::isAllowedUrl($homepage))) {
            $homepage = '';
        }
        $result = [
            'slug' => $slug,
            'version' => $version,
            'package' => $package,
            'mirrors' => array_values(array_unique($mirrors)),
            'sha256' => $sha,
            'expires' => $expires,
            'url' => $homepage,
        ];
        foreach (['requires', 'requires_php', 'tested', 'name', 'author', 'last_updated'] as $key) {
            if (isset($raw[$key]) && is_string($raw[$key])) {
                $result[$key] = substr($raw[$key], 0, 500);
            }
        }
        if (isset($raw['sections']) && is_array($raw['sections'])) {
            $sections = [];
            foreach (['description', 'installation', 'changelog', 'faq'] as $key) {
                if (isset($raw['sections'][$key]) && is_string($raw['sections'][$key])) {
                    $sections[$key] = substr($raw['sections'][$key], 0, 20000);
                }
            }
            $result['sections'] = $sections;
        }
        return $result;
    }

    /** @param list<string> $allowedHosts */
    public static function isAllowedUrl(string $url, array $allowedHosts = []): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) ||
            strtolower((string) ($parts['scheme'] ?? '')) !== 'https' ||
            isset($parts['user']) || isset($parts['pass']) ||
            filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || isset($parts['port']) || filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }
        return $allowedHosts === [] || in_array($host, array_map('strtolower', $allowedHosts), true);
    }
}
