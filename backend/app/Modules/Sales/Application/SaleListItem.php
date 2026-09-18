<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

final readonly class SaleListItem
{
    public function __construct(
        public int $id,
        public int $createdBy,
        public string $createdAt,
        public string $state,
        public string $paymentStatus,
        public string $total,
        public int $itemCount,
    ) {}
}
