<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

use DateTimeImmutable;

final readonly class InventoryLotFilters
{
    public function __construct(
        public ?int $medicineId,
        public ?DateTimeImmutable $expiresBefore,
        public ?bool $available,
        public int $page,
        public int $perPage,
    ) {}
}
