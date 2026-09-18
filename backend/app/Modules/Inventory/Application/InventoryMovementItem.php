<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

final readonly class InventoryMovementItem
{
    public function __construct(
        public int $movementId,
        public int $medicineId,
        public string $genericName,
        public int $lotId,
        public int $actorUserId,
        public string $actorFullName,
        public string $movementType,
        public int $quantityDelta,
        public ?string $reason,
        public ?string $sourceType,
        public ?int $sourceId,
        public string $occurredAt,
    ) {}
}
