<?php
/**
 * Runs in a throwaway WordPress installation via WP-CLI only.
 * All URLs are intercepted; no public release, API or signing key is used.
 * Proves the actual Plugin_Upgrader consumes a signed, hash-checked mirror ZIP.
 */
if (!defined('ABSPATH') || !defined('WP_CLI')) {
    throw new RuntimeException('Run in disposable WordPress under WP-CLI only.');
}
$slug = 'simple-maintenance-mode';
$pluginFile = 'simple-maintenance-mode/simple-maintenance-mode.php';
$primaryApi = 'https://api.nightmarketplugins.com/api/v1/plugins/'.$slug.'/update';
$backupApi = 'https://updates.thepluginmarket.com/api/v1/plugins/'.$slug.'/update';
$packageUrl = 'https://github.com/makingtheimpact/simple-maintenance-mode/releases/download/v2.0.1/simple-maintenance-mode.zip';
$mirrorUrl = 'https://downloads.thepluginmarket.com/simple-maintenance-mode/v2.0.1.zip';
$fixture = '/tmp/smm-fixture';
$zip = $fixture.'/simple-maintenance-mode-2.0.1.zip';

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('Signed WordPress upgrade: '.$message);
    }
};
$assert(is_plugin_active($pluginFile), 'plugin active before upgrade');
$assert(SMM_VERSION === '2.0.0', 'starting at original v2 version');
$assert(has_filter('update_plugins_api.nightmarketplugins.com') !== false,
    'installed plugin registers canonical update hook after test key provisioning');

$envelope = json_decode((string) file_get_contents($fixture.'/signed.json'), true);
$assert(is_array($envelope) && is_file($zip), 'test ZIP and signed metadata exist');
$payload = json_decode($envelope['payload'], true);
$assert($payload['sha256'] === hash_file('sha256', $zip), 'ZIP matches signed digest');
$assert($payload['package'] === $packageUrl, 'signed primary package points to test route');
$assert(($payload['mirrors'][0] ?? '') === $mirrorUrl, 'signed mirror points to test route');

$GLOBALS['smm_ci_requests'] = [];
add_filter('pre_http_request', static function ($preempt, $args, $url) use (
    $primaryApi, $backupApi, $packageUrl, $mirrorUrl, $envelope, $zip
) {
    if (!in_array($url, [$primaryApi, $backupApi, $packageUrl, $mirrorUrl], true)) {
        return $preempt;
    }
    $GLOBALS['smm_ci_requests'][] = $url;

    // Synthetic primary is reachable but compromised/malformed; client must fail over.
    if ($url === $primaryApi) {
        return [
            'headers' => [], 'body' => '{"key_id":"ci-only","payload":"bad","signature":"invalid"}',
            'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [],
        ];
    }
    if ($url === $backupApi) {
        return [
            'headers' => [], 'body' => json_encode($envelope),
            'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [],
        ];
    }

    // Simulate the primary ZIP being changed while the signed mirror stays valid.
    $content = $url === $packageUrl ? 'TAMPERED BYTES MUST BE REJECTED' : file_get_contents($zip);
    if (!empty($args['filename']) && is_string($args['filename'])) {
        file_put_contents($args['filename'], $content);
        return [
            'headers' => ['content-type' => 'application/zip'],
            'body' => '',
            'response' => ['code' => 200, 'message' => 'OK'],
            'cookies' => [],
            'filename' => $args['filename'],
        ];
    }
    return [
        'headers' => ['content-type' => 'application/zip'], 'body' => $content,
        'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [],
    ];
}, 10, 3);

// Check the exact registered native provider hook, not a made-up updater method.
$offer = apply_filters(
    'update_plugins_api.nightmarketplugins.com',
    false,
    ['UpdateURI' => 'https://api.nightmarketplugins.com/plugins/simple-maintenance-mode/',
     'Version' => '2.0.0'],
    $pluginFile,
    []
);
$assert(is_array($offer) && ($offer['version'] ?? null) === '2.0.1',
    'signed backup metadata offers the new version');
$assert(in_array($primaryApi, $GLOBALS['smm_ci_requests'], true), 'primary endpoint checked');
$assert(in_array($backupApi, $GLOBALS['smm_ci_requests'], true), 'secondary endpoint used');

// Feed the offer to WordPress's ordinary upgrade operation. The actual updater
// must intercept the downloaded ZIP and enforce signed SHA-256 verification.
$updates = new stdClass();
$updates->last_checked = time();
$updates->checked = [$pluginFile => '2.0.0'];
$updates->response = [$pluginFile => (object) ($offer + ['new_version' => $offer['version']])];
$updates->no_update = [];
$updates->translations = [];
set_site_transient('update_plugins', $updates);

require_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php';
$upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
$result = $upgrader->upgrade($pluginFile);
$assert($result !== false && !is_wp_error($result), 'Plugin_Upgrader completed a verified update');
$assert(in_array($packageUrl, $GLOBALS['smm_ci_requests'], true), 'primary ZIP was attempted');
$assert(in_array($mirrorUrl, $GLOBALS['smm_ci_requests'], true), 'signed mirror was used after bad ZIP');
$assert(get_option('simple_maintenance_mode_bypass_token') === 'ci-token-keep-unchanged',
    'bypass token remains intact after plugin upgrade');
$assert(get_option('smm_layout') === 'split-left',
    'layout setting remains intact after plugin upgrade');

WP_CLI::success('Signed failover metadata, ZIP hash enforcement and native WordPress upgrade passed.');
