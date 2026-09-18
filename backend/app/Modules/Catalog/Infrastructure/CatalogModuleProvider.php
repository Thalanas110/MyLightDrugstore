<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure;

use App\Modules\Catalog\Application\MedicineListQuery;
use App\Modules\Catalog\Infrastructure\Persistence\EloquentMedicineListQuery;
use App\Modules\ModuleName;
use App\Modules\ModuleProvider;
use Illuminate\Contracts\Container\Container;

final class CatalogModuleProvider implements ModuleProvider
{
    public function module(): ModuleName
    {
        return ModuleName::Catalog;
    }

    public function register(Container $container): void
    {
        $container->bind(MedicineListQuery::class, EloquentMedicineListQuery::class);
    }
}
