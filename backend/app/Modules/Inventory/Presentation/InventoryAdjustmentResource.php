<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation;

use App\Modules\Inventory\Application\InventoryAdjustmentResult;

final readonly class InventoryAdjustmentResource
{
    public function __construct(private InventoryAdjustmentResult $result) {}

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'adjustmentId' => $this->result->adjustmentId,
            'reason' => $this->result->reason->value,
            'itemCount' => $this->result->itemCount,
            'totalQuantityDelta' => $this->result->totalQuantityDelta,
        ];
    }
}
