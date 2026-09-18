<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

final readonly class AddSaleItemCommand
{
    public function __construct(
        public int $actorUserId,
        public int $saleId,
        public string $idempotencyKey,
        public int $medicineId,
        public int $quantity,
    ) {}
}
