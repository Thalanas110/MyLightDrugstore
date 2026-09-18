<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation;

use App\Modules\Inventory\Application\InventoryMovementItem;

final readonly class InventoryMovementResource
{
    public function __construct(private InventoryMovementItem $item) {}

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'movementId' => $this->item->movementId,
            'medicineId' => $this->item->medicineId,
            'genericName' => $this->item->genericName,
            'lotId' => $this->item->lotId,
            'actorUserId' => $this->item->actorUserId,
            'actorFullName' => $this->item->actorFullName,
            'movementType' => $this->item->movementType,
            'quantityDelta' => $this->item->quantityDelta,
            'reason' => $this->item->reason,
            'sourceType' => $this->item->sourceType,
            'sourceId' => $this->item->sourceId,
            'occurredAt' => $this->item->occurredAt,
        ];
    }
}
