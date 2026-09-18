<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

use App\Modules\Inventory\Domain\InventoryAdjustmentReason;

final readonly class InventoryAdjustmentCommand
{
    /**
     * @param  list<InventoryAdjustmentItem>  $items
     */
    public function __construct(
        public int $actorUserId,
        public InventoryAdjustmentReason $reason,
        public array $items,
    ) {}
}
