<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

final readonly class CreateSaleItem
{
    public function __construct(
        public int $medicineId,
        public int $quantity,
    ) {}
}
