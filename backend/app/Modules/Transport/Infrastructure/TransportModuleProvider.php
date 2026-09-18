<?php

declare(strict_types=1);

namespace App\Modules\Transport\Infrastructure;

use App\Modules\ModuleName;
use App\Modules\ModuleProvider;
use Illuminate\Contracts\Container\Container;

final class TransportModuleProvider implements ModuleProvider
{
    public function module(): ModuleName
    {
        return ModuleName::Transport;
    }

    public function register(Container $container): void {}
}
