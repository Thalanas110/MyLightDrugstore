<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure;

use App\Modules\ModuleName;
use App\Modules\ModuleProvider;
use App\Modules\Sales\Application\SaleCreator;
use App\Modules\Sales\Application\SaleDetailsQuery;
use App\Modules\Sales\Application\SaleItemAdder;
use App\Modules\Sales\Application\SaleItemRemover;
use App\Modules\Sales\Application\SaleListQuery;
use App\Modules\Sales\Infrastructure\Persistence\EloquentSaleCreator;
use App\Modules\Sales\Infrastructure\Persistence\EloquentSaleDetailsQuery;
use App\Modules\Sales\Infrastructure\Persistence\EloquentSaleItemAdder;
use App\Modules\Sales\Infrastructure\Persistence\EloquentSaleItemRemover;
use App\Modules\Sales\Infrastructure\Persistence\EloquentSaleListQuery;
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
        $container->bind(SaleItemAdder::class, EloquentSaleItemAdder::class);
        $container->bind(SaleItemRemover::class, EloquentSaleItemRemover::class);
        $container->bind(SaleDetailsQuery::class, EloquentSaleDetailsQuery::class);
        $container->bind(SaleListQuery::class, EloquentSaleListQuery::class);
    }
}
