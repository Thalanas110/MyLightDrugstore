<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure;

use App\Modules\ModuleName;
use App\Modules\ModuleProvider;
use Illuminate\Contracts\Container\Container;

final class OperationsModuleProvider implements ModuleProvider
{
    public function module(): ModuleName
    {
        return ModuleName::Operations;
    }

    public function register(Container $container): void {}
}
