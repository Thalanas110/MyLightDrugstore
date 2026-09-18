<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

final readonly class InventorySummaryItem
{
    public function __construct(
        public int $medicineId,
        public string $genericName,
        public ?string $brandName,
        public string $dosageForm,
        public ?string $strength,
        public string $unitPrice,
        public int $stockOnHand,
        public bool $lowStock,
        public ?string $earliestExpiry,
    ) {}
}
