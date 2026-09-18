<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure;

use App\Modules\ModuleName;
use App\Modules\ModuleProvider;
use App\Modules\Sales\Application\SaleCreator;
use App\Modules\Sales\Application\SaleDetailsQuery;
use App\Modules\Sales\Infrastructure\Persistence\EloquentSaleCreator;
use App\Modules\Sales\Infrastructure\Persistence\EloquentSaleDetailsQuery;
use Illuminate\Contracts\Container\Container;

final class SalesModuleProvider implements ModuleProvider
{
    public function module(): ModuleName
    {
        return ModuleName::Sales;
    }

    public function register(Container $container): void
    {
        $container->bind(SaleCreator::class, EloquentSaleCreator::class);
        $container->bind(SaleDetailsQuery::class, EloquentSaleDetailsQuery::class);
    }
}
