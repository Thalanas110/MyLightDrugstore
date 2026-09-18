<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

use DateTimeImmutable;

final readonly class ReceiveStockItem
{
    public function __construct(
        public int $medicineId,
        public int $quantity,
        public DateTimeImmutable $expiresAt,
    ) {}
}
