<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

final readonly class InventoryAdjustmentItem
{
    public function __construct(
        public int $lotId,
        public int $quantityDelta,
    ) {}
}
