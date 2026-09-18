<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Infrastructure;

use App\Modules\ModuleName;
use App\Modules\ModuleProvider;
use Illuminate\Contracts\Container\Container;

final class ReportingModuleProvider implements ModuleProvider
{
    public function module(): ModuleName
    {
        return ModuleName::Reporting;
    }

    public function register(Container $container): void {}
}
