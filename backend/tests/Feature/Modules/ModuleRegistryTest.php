<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use App\Modules\ModuleName;
use App\Modules\ModuleRegistry;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Tests\Fixtures\RecordingModuleProvider;
use Tests\TestCase;

final class ModuleRegistryTest extends TestCase
{
    private RecordingModuleProvider $catalogModuleProvider;

    private RecordingModuleProvider $inventoryModuleProvider;

    public function createApplication(): Application
    {
        $application = require Application::inferBasePath().'/bootstrap/app.php';

        $this->traitsUsedByTest = class_uses_recursive(self::class);
        $this->catalogModuleProvider = new RecordingModuleProvider(ModuleName::Catalog);
        $this->inventoryModuleProvider = new RecordingModuleProvider(ModuleName::Inventory);

        $application->booting(function (Application $application): void {
            $application->instance(
                ModuleRegistry::class,
                new ModuleRegistry($application, [
                    $this->catalogModuleProvider,
                    $this->inventoryModuleProvider,
                ]),
            );
        });
        $application->make(Kernel::class)->bootstrap();

        return $application;
    }

    public function test_application_boot_registers_every_configured_module_once(): void
    {
        $this->assertSame(1, $this->catalogModuleProvider->registrationCount);
        $this->assertSame(1, $this->inventoryModuleProvider->registrationCount);
    }

    public function test_registry_does_not_register_modules_again_after_boot(): void
    {
        $this->app->make(ModuleRegistry::class)->register();

        $this->assertSame(1, $this->catalogModuleProvider->registrationCount);
        $this->assertSame(1, $this->inventoryModuleProvider->registrationCount);
    }
}
