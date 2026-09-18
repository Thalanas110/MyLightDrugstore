<?php

namespace App\Providers;

use App\Modules\ModuleProvider;
use App\Modules\ModuleRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use LogicException;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, function (Application $application): ModuleRegistry {
            $entryPoints = config('modules.entry_points', []);

            if (! is_array($entryPoints) || ! array_is_list($entryPoints)) {
                throw new LogicException('The module entry points configuration must be a list.');
            }

            $providers = [];

            foreach ($entryPoints as $entryPoint) {
                if (! is_string($entryPoint) || ! is_file($entryPoint)) {
                    throw new LogicException('Every configured module entry point must be an existing file.');
                }

                $providerClass = require $entryPoint;

                if (! is_string($providerClass) || ! class_exists($providerClass)) {
                    throw new LogicException("The module entry point [{$entryPoint}] must return a provider class name.");
                }

                $provider = $application->make($providerClass);

                if (! $provider instanceof ModuleProvider) {
                    throw new LogicException("The configured module provider [{$providerClass}] must implement ".ModuleProvider::class.'.');
                }

                $providers[] = $provider;
            }

            return new ModuleRegistry($application, $providers);
        });
    }

    public function boot(ModuleRegistry $registry): void
    {
        $registry->register();
    }
}
