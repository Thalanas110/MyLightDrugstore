<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

final readonly class RestoreSaleItemStockCommand
{
    public function __construct(
        public int $actorUserId,
        public int $saleId,
        public int $saleItemId,
        public int $medicineId,
        public int $quantity,
    ) {}
}
