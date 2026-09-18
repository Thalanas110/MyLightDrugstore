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
            $providerClasses = config('modules.providers', []);

            if (! is_array($providerClasses) || ! array_is_list($providerClasses)) {
                throw new LogicException('The module providers configuration must be a list.');
            }

            $providers = [];

            foreach ($providerClasses as $providerClass) {
                if (! is_string($providerClass)) {
                    throw new LogicException('Every configured module provider must be a class name.');
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
