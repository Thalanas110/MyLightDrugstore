<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Presentation;

use App\Modules\Catalog\Application\MedicineListItem;

final readonly class MedicineListResource
{
    public function __construct(private MedicineListItem $item) {}

    /** @return array<string, bool|int|string|null> */
    public function toArray(): array
    {
        return [
            'id' => $this->item->id,
            'genericName' => $this->item->genericName,
            'brandName' => $this->item->brandName,
            'description' => $this->item->description,
            'dosageForm' => $this->item->dosageForm,
            'strength' => $this->item->strength,
            'unitPrice' => $this->item->unitPrice,
            'storageLocation' => $this->item->storageLocation,
            'active' => $this->item->active,
            'stockOnHand' => $this->item->stockOnHand,
            'createdAt' => $this->item->createdAt,
            'updatedAt' => $this->item->updatedAt,
        ];
    }
}
