<?php
declare(strict_types=1);

namespace MTI\Plugins\SimpleMaintenanceMode\Framework\Update;

/**
 * Per-plugin, self-contained updater using the WordPress Update URI header.
 * Consumer plugins must vendor/prefix this class to avoid cross-plugin conflicts.
 */
final class Client
{
    private string $pluginFile;
    private string $slug;
    private string $updateUri;
    private string $endpoint;
    /** @var list<string> */
    private array $packageHosts;

    /** @param list<string> $packageHosts */
    public function __construct(string $pluginFile, string $slug, string $updateUri, string $endpoint, array $packageHosts)
    {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) {
            throw new \InvalidArgumentException('Invalid plugin slug.');
        }
        if (!Metadata::isAllowedUrl($updateUri) || !Metadata::isAllowedUrl($endpoint) || $packageHosts === []) {
            throw new \InvalidArgumentException('HTTPS update URI, endpoint, and package hosts are required.');
        }
        $this->pluginFile = $pluginFile;
        $this->slug = $slug;
        $this->updateUri = $updateUri;
        $this->endpoint = $endpoint;
        $this->packageHosts = $packageHosts;
    }

    public function register(): void
    {
        $host = strtolower((string) parse_url($this->updateUri, PHP_URL_HOST));
        add_filter('update_plugins_' . $host, [$this, 'filterUpdate'], 10, 4);
        add_filter('plugins_api', [$this, 'filterPluginInfo'], 10, 3);
    }

    /**
     * @param array<string, mixed>|false $update
     * @param array<string, mixed> $pluginData
     * @param list<string> $locales
     * @return array<string, mixed>|false
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

    /**
     * @param mixed $result
     * @param mixed $args
     * @return mixed
     */
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

    /** @return array<string, mixed>|null */
    private function getMetadata(): ?array
    {
        $cacheKey = 'mti_update_' . substr(hash('sha256', $this->endpoint . $this->slug), 0, 20);
        $cached = get_site_transient($cacheKey);
        if (is_array($cached) && array_key_exists('data', $cached)) {
            return is_array($cached['data']) ? $cached['data'] : null;
        }

        $response = wp_safe_remote_get($this->endpoint, [
            'timeout' => 8,
            'redirection' => 2,
            'sslverify' => true,
            'limit_response_size' => 131072,
            'headers' => ['Accept' => 'application/json'],
        ]);
        $data = null;
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $raw = json_decode(wp_remote_retrieve_body($response), true);
            $data = Metadata::normalize($raw, $this->slug, $this->packageHosts);
        }
        set_site_transient($cacheKey, ['data' => $data], $data === null ? 15 * MINUTE_IN_SECONDS : 6 * HOUR_IN_SECONDS);
        return $data;
    }
}
