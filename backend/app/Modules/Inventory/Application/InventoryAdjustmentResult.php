<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

use App\Modules\Inventory\Domain\InventoryAdjustmentReason;

final readonly class InventoryAdjustmentResult
{
    public function __construct(
        public int $adjustmentId,
        public InventoryAdjustmentReason $reason,
        public int $itemCount,
        public int $totalQuantityDelta,
    ) {}
}
