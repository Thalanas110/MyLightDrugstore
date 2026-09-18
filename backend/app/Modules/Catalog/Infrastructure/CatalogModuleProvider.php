<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure;

use App\Modules\Catalog\Application\MedicineArchiver;
use App\Modules\Catalog\Application\MedicineCreator;
use App\Modules\Catalog\Application\MedicineListQuery;
use App\Modules\Catalog\Application\MedicineUpdater;
use App\Modules\Catalog\Infrastructure\Persistence\EloquentMedicineArchiver;
use App\Modules\Catalog\Infrastructure\Persistence\EloquentMedicineCreator;
use App\Modules\Catalog\Infrastructure\Persistence\EloquentMedicineListQuery;
use App\Modules\Catalog\Infrastructure\Persistence\EloquentMedicineUpdater;
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
        $container->bind(MedicineCreator::class, EloquentMedicineCreator::class);
        $container->bind(MedicineArchiver::class, EloquentMedicineArchiver::class);
        $container->bind(MedicineUpdater::class, EloquentMedicineUpdater::class);
        $container->bind(MedicineListQuery::class, EloquentMedicineListQuery::class);
    }
}
