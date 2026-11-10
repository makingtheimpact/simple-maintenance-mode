<?php
declare(strict_types=1);

namespace MTI\Plugins\SimpleMaintenanceMode\Framework\Update;

/**
 * Validates directory metadata before it reaches WordPress's plugin updater.
 * This class has no WordPress dependencies and can be unit tested in isolation.
 */
final class Metadata
{
    /**
     * @param mixed $raw Decoded update API JSON.
     * @param list<string> $allowedHosts Exact hosts permitted to deliver ZIPs.
     * @return array<string, mixed>|null
     */
    public static function normalize($raw, string $slug, array $allowedHosts): ?array
    {
        if (!is_array($raw) || ($raw['slug'] ?? null) !== $slug) {
            return null;
        }

        $version = $raw['version'] ?? null;
        if (!is_string($version) || !preg_match('/^\d+\.\d+\.\d+(?:[-+][a-zA-Z0-9.-]+)?$/D', $version)) {
            return null;
        }

        $package = $raw['package'] ?? null;
        if (!is_string($package) || !self::isAllowedUrl($package, $allowedHosts)) {
            return null;
        }

        $homepage = $raw['homepage'] ?? '';
        if (!is_string($homepage) || !self::isAllowedUrl($homepage)) {
            $homepage = '';
        }

        $result = [
            'slug' => $slug,
            'version' => $version,
            'package' => $package,
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
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || isset($parts['user']) || isset($parts['pass']) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return $allowedHosts === [] || in_array($host, array_map('strtolower', $allowedHosts), true);
    }
}
