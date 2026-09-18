<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

use DateTimeImmutable;

final readonly class InventoryReceiptResult
{
    public function __construct(
        public int $receiptId,
        public int $itemCount,
        public int $totalQuantity,
        public DateTimeImmutable $receivedAt,
    ) {}
}
