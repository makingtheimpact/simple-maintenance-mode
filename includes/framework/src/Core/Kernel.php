<?php
declare(strict_types=1);

namespace MTI\Plugins\SimpleMaintenanceMode\Framework\Core;

/**
 * Boots registered plugin feature modules once, without global singleton state.
 */
final class Kernel
{
    /** @var list<Module> */
    private array $modules = [];
    private bool $booted = false;

    public function add(Module $module): self
    {
        if ($this->booted) {
            throw new \LogicException('Modules must be registered before boot.');
        }
        $this->modules[] = $module;
        return $this;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;
        foreach ($this->modules as $module) {
            $module->boot();
        }
    }
}
