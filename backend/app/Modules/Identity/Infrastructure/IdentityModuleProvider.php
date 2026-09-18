<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Modules\ModuleName;
use App\Modules\ModuleProvider;
use Illuminate\Contracts\Container\Container;

final class IdentityModuleProvider implements ModuleProvider
{
    public function module(): ModuleName
    {
        return ModuleName::Identity;
    }

    public function register(Container $container): void {}
}
