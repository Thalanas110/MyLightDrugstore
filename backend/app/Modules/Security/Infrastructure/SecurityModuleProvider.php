<?php

declare(strict_types=1);

namespace App\Modules\Security\Infrastructure;

use App\Modules\ModuleName;
use App\Modules\ModuleProvider;
use Illuminate\Contracts\Container\Container;

final class SecurityModuleProvider implements ModuleProvider
{
    public function module(): ModuleName
    {
        return ModuleName::Security;
    }

    public function register(Container $container): void {}
}
