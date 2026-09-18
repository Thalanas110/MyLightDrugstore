<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

use DateTimeImmutable;

final readonly class MedicineListFilters
{
    public function __construct(
        public ?string $query,
        public bool $active,
        public ?bool $lowStock,
        public ?DateTimeImmutable $expiresBefore,
        public int $page,
        public int $perPage,
    ) {}
}
