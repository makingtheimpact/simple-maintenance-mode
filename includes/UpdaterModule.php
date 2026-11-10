<?php
declare(strict_types=1);

namespace MTI\Plugins\SimpleMaintenanceMode;

use MTI\Plugins\SimpleMaintenanceMode\Framework\Core\Module;
use MTI\Plugins\SimpleMaintenanceMode\Framework\Update\Client;

/**
 * Opts this plugin into its own update channel without changing legacy hooks.
 * The update endpoint is planned, not deployed as of this integration.
 */
final class UpdaterModule implements Module
{
    public function boot(): void
    {
        (new Client(
            plugin_basename(SMM_FILE),
            'simple-maintenance-mode',
            'https://plugins.makingtheimpact.com/simple-maintenance-mode/',
            'https://plugins.makingtheimpact.com/api/v1/plugins/simple-maintenance-mode/update',
            ['github.com']
        ))->register();
    }
}
