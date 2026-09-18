<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure;

use App\Modules\Inventory\Application\InventoryAdjustmentWriter;
use App\Modules\Inventory\Application\InventoryLotQuery;
use App\Modules\Inventory\Application\InventoryMovementQuery;
use App\Modules\Inventory\Application\InventoryReceiptWriter;
use App\Modules\Inventory\Application\InventoryStockReserver;
use App\Modules\Inventory\Application\InventorySummaryQuery;
use App\Modules\Inventory\Infrastructure\Persistence\EloquentInventoryAdjustmentWriter;
use App\Modules\Inventory\Infrastructure\Persistence\EloquentInventoryLotQuery;
use App\Modules\Inventory\Infrastructure\Persistence\EloquentInventoryMovementQuery;
use App\Modules\Inventory\Infrastructure\Persistence\EloquentInventoryReceiptWriter;
use App\Modules\Inventory\Infrastructure\Persistence\EloquentInventoryStockReserver;
use App\Modules\Inventory\Infrastructure\Persistence\EloquentInventorySummaryQuery;
use App\Modules\ModuleName;
use App\Modules\ModuleProvider;
use Illuminate\Contracts\Container\Container;

final class InventoryModuleProvider implements ModuleProvider
{
    public function module(): ModuleName
    {
        return ModuleName::Inventory;
    }

    public function register(Container $container): void
    {
        $container->bind(InventoryReceiptWriter::class, EloquentInventoryReceiptWriter::class);
        $container->bind(InventoryLotQuery::class, EloquentInventoryLotQuery::class);
        $container->bind(InventoryMovementQuery::class, EloquentInventoryMovementQuery::class);
        $container->bind(InventoryAdjustmentWriter::class, EloquentInventoryAdjustmentWriter::class);
        $container->bind(InventorySummaryQuery::class, EloquentInventorySummaryQuery::class);
        $container->bind(InventoryStockReserver::class, EloquentInventoryStockReserver::class);
    }
}
