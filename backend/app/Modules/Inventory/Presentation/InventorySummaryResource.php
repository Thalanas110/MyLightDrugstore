<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation;

use App\Modules\Inventory\Application\InventorySummaryItem;

final readonly class InventorySummaryResource
{
    public function __construct(private InventorySummaryItem $item) {}

    /** @return array<string, bool|int|string|null> */
    public function toArray(): array
    {
        return [
            'medicineId' => $this->item->medicineId,
            'genericName' => $this->item->genericName,
            'brandName' => $this->item->brandName,
            'dosageForm' => $this->item->dosageForm,
            'strength' => $this->item->strength,
            'unitPrice' => $this->item->unitPrice,
            'stockOnHand' => $this->item->stockOnHand,
            'lowStock' => $this->item->lowStock,
            'earliestExpiry' => $this->item->earliestExpiry,
        ];
    }
}
