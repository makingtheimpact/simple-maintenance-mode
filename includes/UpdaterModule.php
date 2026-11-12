<?php
declare(strict_types=1);

namespace MTI\Plugins\SimpleMaintenanceMode;

use MTI\Plugins\SimpleMaintenanceMode\Framework\Core\Module;
use MTI\Plugins\SimpleMaintenanceMode\Framework\Update\Client;

/**
 * Self-contained signed updater. Until a trusted public key ships in the
 * reviewed plugin manifest, no update hooks or network calls are registered.
 * A broken update service must never interrupt maintenance-mode functions.
 */
final class UpdaterModule implements Module
{
    public function boot(): void
    {
        $raw = @file_get_contents(SMM_DIR.'mti-plugin.json');
        if (!is_string($raw) || $raw === '' || strlen($raw) > 65536) {
            return;
        }
        $manifest = json_decode($raw, true);
        if (!is_array($manifest) ||
            ($manifest['slug'] ?? '') !== 'simple-maintenance-mode' ||
            ($manifest['update_uri'] ?? '') !==
                'https://api.nightmarketplugins.com/plugins/simple-maintenance-mode/' ||
            empty($manifest['public_keys']) || !is_array($manifest['public_keys'])) {
            return;
        }

        try {
            (new Client(
                plugin_basename(SMM_FILE),
                'simple-maintenance-mode',
                'https://api.nightmarketplugins.com/plugins/simple-maintenance-mode/',
                $manifest['update_endpoints'] ?? [],
                $manifest['package_hosts'] ?? [],
                $manifest['public_keys']
            ))->register();
        } catch (\InvalidArgumentException $exception) {
            // Update configuration errors must not block the site's operation.
        }
    }
}
