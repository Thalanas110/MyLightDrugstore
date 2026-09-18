<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

interface InventoryStockReserver
{
    public function reserveForSale(ReserveStockForSaleCommand $command): void;
}
