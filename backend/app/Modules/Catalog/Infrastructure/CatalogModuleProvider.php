<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure;

use App\Modules\ModuleName;
use App\Modules\ModuleProvider;
use Illuminate\Contracts\Container\Container;

final class CatalogModuleProvider implements ModuleProvider
{
    public function module(): ModuleName
    {
        return ModuleName::Catalog;
    }

    public function register(Container $container): void {}
}
