<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure;

use App\Modules\Inventory\Application\InventoryReceiptWriter;
use App\Modules\Inventory\Infrastructure\Persistence\EloquentInventoryReceiptWriter;
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
    }
}
