<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

use DateTimeImmutable;

final readonly class InventorySummaryFilters
{
    public function __construct(
        public ?bool $lowStock,
        public ?DateTimeImmutable $expiresBefore,
        public int $page,
        public int $perPage,
    ) {}
}
