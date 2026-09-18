<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

final readonly class ReserveSaleStockItem
{
    public function __construct(
        public int $medicineId,
        public int $quantity,
    ) {}
}
