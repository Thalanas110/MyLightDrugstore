<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation;

use App\Modules\Inventory\Application\InventoryLotItem;

final readonly class InventoryLotResource
{
    public function __construct(private InventoryLotItem $item) {}

    /** @return array<string, bool|int|string|null> */
    public function toArray(): array
    {
        return [
            'lotId' => $this->item->lotId,
            'medicineId' => $this->item->medicineId,
            'genericName' => $this->item->genericName,
            'brandName' => $this->item->brandName,
            'receivedAt' => $this->item->receivedAt,
            'expiresAt' => $this->item->expiresAt,
            'quantityReceived' => $this->item->quantityReceived,
            'quantityRemaining' => $this->item->quantityRemaining,
            'available' => $this->item->available,
        ];
    }
}
