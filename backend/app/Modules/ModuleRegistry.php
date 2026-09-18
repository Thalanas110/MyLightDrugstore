<?php

declare(strict_types=1);

namespace App\Modules;

use Illuminate\Contracts\Container\Container;

final class ModuleRegistry
{
    /** @var array<string, true> */
    private array $registeredModules = [];

    /**
     * @param  list<ModuleProvider>  $providers
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $providers,
    ) {}

    public function register(): void
    {
        foreach ($this->providers as $provider) {
            $moduleName = $provider->module()->value;

            if (isset($this->registeredModules[$moduleName])) {
                continue;
            }

            $provider->register($this->container);
            $this->registeredModules[$moduleName] = true;
        }
    }
}
