<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

interface InventoryStockRestorer
{
    public function restoreSaleItemStock(RestoreSaleItemStockCommand $command): void;
}
