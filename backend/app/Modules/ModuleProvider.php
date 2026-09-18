<?php

declare(strict_types=1);

namespace App\Modules;

use Illuminate\Contracts\Container\Container;

interface ModuleProvider
{
    public function module(): ModuleName;

    public function register(Container $container): void;
}
