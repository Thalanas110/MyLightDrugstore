<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

interface InventoryAdjustmentWriter
{
    public function adjust(InventoryAdjustmentCommand $command): InventoryAdjustmentResult;
}
