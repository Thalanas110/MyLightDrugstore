<?php

declare(strict_types=1);

namespace Tests\Architecture\Modules;

use App\Modules\ModuleName;
use App\Modules\ModuleProvider;
use Tests\TestCase;

final class ModuleEntryPointTest extends TestCase
{
    public function test_every_registered_module_has_one_public_entry_point_and_all_layers(): void
    {
        $expectedEntryPoints = array_map(
            static fn (ModuleName $module): string => app_path('Modules/'.$module->name.'/index.php'),
            ModuleName::cases(),
        );
        $actualEntryPoints = glob(app_path('Modules').'/*/index.php') ?: [];

        sort($expectedEntryPoints, SORT_STRING);
        sort($actualEntryPoints, SORT_STRING);

        $this->assertSame($expectedEntryPoints, $actualEntryPoints);

        foreach (ModuleName::cases() as $module) {
            foreach (['Presentation', 'Application', 'Domain', 'Infrastructure'] as $layer) {
                $this->assertDirectoryExists(app_path('Modules/'.$module->name.'/'.$layer));
            }
        }
    }

    public function test_public_entry_points_export_providers_for_their_registered_modules(): void
    {
        $moduleProviders = [];

        foreach (config('modules.entry_points', []) as $entryPoint) {
            $providerClass = require $entryPoint;
            $provider = app($providerClass);

            $this->assertInstanceOf(ModuleProvider::class, $provider);
            $moduleProviders[] = $provider->module();
        }

        $this->assertSame(ModuleName::cases(), $moduleProviders);
    }
}
