<?php
declare(strict_types=1);

namespace MTI\Plugins\SimpleMaintenanceMode\Framework\Core;

/**
 * Small optional module boundary; each plugin owns its features and hooks.
 */
interface Module
{
    public function boot(): void;
}
