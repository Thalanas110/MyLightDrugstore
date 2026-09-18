<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

final readonly class InventoryLotItem
{
    public function __construct(
        public int $lotId,
        public int $medicineId,
        public string $genericName,
        public ?string $brandName,
        public string $receivedAt,
        public string $expiresAt,
        public int $quantityReceived,
        public int $quantityRemaining,
        public bool $available,
    ) {}
}
