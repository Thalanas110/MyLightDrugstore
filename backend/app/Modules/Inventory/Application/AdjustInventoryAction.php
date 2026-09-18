<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

final class AdjustInventoryAction
{
    public function __construct(private readonly InventoryAdjustmentWriter $adjustmentWriter) {}

    public function execute(InventoryAdjustmentCommand $command): InventoryAdjustmentResult
    {
        return $this->adjustmentWriter->adjust($command);
    }
}
