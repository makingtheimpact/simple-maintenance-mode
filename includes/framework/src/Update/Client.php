<?php
declare(strict_types=1);

namespace MTI\Plugins\SimpleMaintenanceMode\Framework\Update;

/**
 * Per-plugin, independent updater for external Update URI providers.
 * Signed metadata is required; a corrupt/offline server never blocks the plugin itself.
 * Package verification is enforced through upgrader_pre_download.
 */
final class Client
{
    private string $pluginFile;
    private string $slug;
    private string $updateUri;
    /** @var list<string> */
    private array $endpoints;
    /** @var list<string> */
    private array $packageHosts;
    /** @var array<string,string> */
    private array $publicKeys;

    /**
     * @param list<string> $endpoints Primary first; secondary independent endpoint second.
     * @param list<string> $packageHosts Exact allowed package hosts.
     * @param array<string,string> $publicKeys Public Ed25519 key IDs and base64 keys.
     */
    public function __construct(
        string $pluginFile, string $slug, string $updateUri,
        array $endpoints, array $packageHosts, array $publicKeys
    ) {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) ||
            !Metadata::isAllowedUrl($updateUri) ||
            $endpoints === [] || count($endpoints) > 3 ||
            $packageHosts === [] || array_values($endpoints) !== $endpoints) {
            throw new \InvalidArgumentException('Invalid updater configuration.');
        }
        foreach ($endpoints as $endpoint) {
            if (!is_string($endpoint) || !Metadata::isAllowedUrl($endpoint)) {
                throw new \InvalidArgumentException('Each endpoint must be HTTPS.');
            }
        }
        foreach ($packageHosts as $host) {
            if (!is_string($host) || !preg_match('/^[a-z0-9.-]+$/iD', $host)) {
                throw new \InvalidArgumentException('Invalid package host.');
            }
        }
        foreach ($publicKeys as $id => $key) {
            if (!is_string($id) || !preg_match('/^[a-zA-Z0-9_-]{1,40}$/D', $id) ||
                !is_string($key) || strlen((string) base64_decode($key, true)) !== 32) {
                throw new \InvalidArgumentException('Invalid public signing key.');
            }
        }
        $this->pluginFile = $pluginFile;
        $this->slug = $slug;
        $this->updateUri = $updateUri;
        $this->endpoints = array_values($endpoints);
        $this->packageHosts = $packageHosts;
        $this->publicKeys = $publicKeys;
    }

    public function register(): void
    {
        $host = strtolower((string) parse_url($this->updateUri, PHP_URL_HOST));
        add_filter('update_plugins_' . $host, [$this, 'filterUpdate'], 10, 4);
        add_filter('plugins_api', [$this, 'filterPluginInfo'], 10, 3);
        add_filter('upgrader_pre_download', [$this, 'filterPackageDownload'], 5, 4);
    }

    /**
     * @param mixed $update
     * @param array<string,mixed> $pluginData
     * @param list<string> $locales
     * @return array<string,mixed>|false|mixed
     */
    public function filterUpdate($update, array $pluginData, string $pluginFile, array $locales)
    {
        if ($pluginFile !== $this->pluginFile || ($pluginData['UpdateURI'] ?? '') !== $this->updateUri) {
            return $update;
        }
        $data = $this->getMetadata();
        if ($data === null || !version_compare($data['version'], (string) ($pluginData['Version'] ?? '0.0.0'), '>')) {
            return false;
        }
        return [
            'id' => $this->updateUri,
            'slug' => $this->slug,
            'version' => $data['version'],
            'url' => $data['url'],
            'package' => $data['package'],
            'tested' => $data['tested'] ?? '',
            'requires_php' => $data['requires_php'] ?? '',
        ];
    }

    /** @param mixed $result @param mixed $args @return mixed */
    public function filterPluginInfo($result, string $action, $args)
    {
        if ($action !== 'plugin_information' || !is_object($args) || ($args->slug ?? '') !== $this->slug) {
            return $result;
        }
        $data = $this->getMetadata();
        if ($data === null) {
            return $result;
        }
        $sections = [];
        foreach (($data['sections'] ?? []) as $key => $html) {
            $sections[$key] = function_exists('wp_kses_post') ? wp_kses_post($html) : strip_tags($html);
        }
        return (object) [
            'name' => $data['name'] ?? $this->slug,
            'slug' => $this->slug,
            'version' => $data['version'],
            'author' => $data['author'] ?? '',
            'homepage' => $data['url'],
            'requires' => $data['requires'] ?? '',
            'tested' => $data['tested'] ?? '',
            'requires_php' => $data['requires_php'] ?? '',
            'last_updated' => $data['last_updated'] ?? '',
            'sections' => $sections,
            'download_link' => $data['package'],
        ];
    }

    /**
     * Verify actual ZIP bytes before WP installs an MTI-supplied plugin update.
     * WordPress passes hook_extra['plugin'] for single and bulk upgrades.
     * Bulk upgrades omit 'type' and 'action', so match plugin identity first.
     * @param mixed $reply
     * @param mixed $upgrader
     * @param array<string,mixed> $hookExtra
     * @return mixed
     */
    public function filterPackageDownload($reply, string $package, $upgrader, array $hookExtra)
    {
        if (($hookExtra['plugin'] ?? null) !== $this->pluginFile ||
            (isset($hookExtra['type']) && $hookExtra['type'] !== 'plugin') ||
            (isset($hookExtra['action']) && $hookExtra['action'] !== 'update')) {
            return $reply;
        }
        $metadata = $this->getMetadata();
        if ($metadata === null) {
            return new \WP_Error('mti_unsigned_update', 'Verified update metadata is unavailable.');
        }
        $urls = array_values(array_unique(array_merge([$metadata['package']], $metadata['mirrors'])));
        if (!in_array($package, $urls, true)) {
            return new \WP_Error('mti_package_mismatch', 'The requested update URL is not approved.');
        }
        if ($reply !== false) {
            if (is_string($reply) && is_file($reply) &&
                hash_equals($metadata['sha256'], (string) hash_file('sha256', $reply))) {
                return $reply;
            }
            return new \WP_Error('mti_unverified_download', 'The pre-downloaded update could not be verified.');
        }
        if (!function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        // Try the signed primary package first, then signed mirrors.
        $ordered = array_values(array_unique(array_merge([$package], $urls)));
        foreach ($ordered as $url) {
            $tmp = download_url($url, 120);
            if (is_wp_error($tmp)) {
                continue;
            }
            if (is_string($tmp) && is_file($tmp) &&
                hash_equals($metadata['sha256'], (string) hash_file('sha256', $tmp))) {
                return $tmp;
            }
            if (is_string($tmp) && is_file($tmp)) {
                @unlink($tmp);
            }
        }
        return new \WP_Error('mti_checksum_failed', 'No approved update ZIP passed integrity verification.');
    }

    /** @return array<string,mixed>|null */
    private function getMetadata(): ?array
    {
        // No pinned key = no network request and no automatic update.
        if ($this->publicKeys === []) {
            return null;
        }
        $cacheKey = 'mti_update_' . substr(hash('sha256', $this->updateUri . implode('|', $this->endpoints) .
            json_encode($this->publicKeys)), 0, 20);
        $cached = get_site_transient($cacheKey);
        if (is_array($cached) && array_key_exists('envelope', $cached)) {
            if ($cached['envelope'] === null) {
                return null;
            }
            $verified = SignedEnvelope::verify(
                $cached['envelope'], $this->publicKeys, $this->slug, $this->updateUri, $this->packageHosts
            );
            if ($verified !== null) {
                return $verified;
            }
        }
        foreach ($this->endpoints as $endpoint) {
            $response = wp_safe_remote_get($endpoint, [
                'timeout' => 5,
                'redirection' => 0,
                'sslverify' => true,
                'limit_response_size' => 131072,
                'headers' => ['Accept' => 'application/json'],
            ]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                continue;
            }
            $envelope = json_decode(wp_remote_retrieve_body($response), true);
            $verified = SignedEnvelope::verify(
                $envelope, $this->publicKeys, $this->slug, $this->updateUri, $this->packageHosts
            );
            if ($verified === null) {
                continue;
            }
            $ttl = min(6 * HOUR_IN_SECONDS, max(60, $verified['expires'] - time()));
            set_site_transient($cacheKey, ['envelope' => $envelope], $ttl);
            return $verified;
        }
        set_site_transient($cacheKey, ['envelope' => null], 10 * MINUTE_IN_SECONDS);
        return null;
    }
}
