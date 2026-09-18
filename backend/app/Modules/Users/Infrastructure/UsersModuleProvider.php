<?php

declare(strict_types=1);

namespace App\Modules\Users\Infrastructure;

use App\Modules\ModuleName;
use App\Modules\ModuleProvider;
use Illuminate\Contracts\Container\Container;

final class UsersModuleProvider implements ModuleProvider
{
    public function module(): ModuleName
    {
        return ModuleName::Users;
    }

    public function register(Container $container): void {}
}
