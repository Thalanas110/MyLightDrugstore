<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

use DateTimeImmutable;

final readonly class InventoryMovementFilters
{
    public function __construct(
        public ?int $medicineId,
        public ?int $lotId,
        public ?string $movementType,
        public ?DateTimeImmutable $from,
        public ?DateTimeImmutable $to,
        public int $page,
        public int $perPage,
    ) {}
}
