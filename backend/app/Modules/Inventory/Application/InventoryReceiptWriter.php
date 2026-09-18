<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

interface InventoryReceiptWriter
{
    public function receive(ReceiveStockCommand $command): InventoryReceiptResult;
}
