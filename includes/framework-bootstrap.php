<?php
/**
 * Self-contained framework bootstrap. No global framework plugin required.
 */
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/framework/src/Core/Module.php';
require_once __DIR__ . '/framework/src/Core/Kernel.php';
require_once __DIR__ . '/framework/src/Update/Metadata.php';
require_once __DIR__ . '/framework/src/Update/Client.php';
require_once __DIR__ . '/UpdaterModule.php';

add_action('plugins_loaded', static function (): void {
    $kernel = new \MTI\Plugins\SimpleMaintenanceMode\Framework\Core\Kernel();
    $kernel->add(new \MTI\Plugins\SimpleMaintenanceMode\UpdaterModule());
    $kernel->boot();
}, 20);
