<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

use DateTimeImmutable;

final readonly class SaleListFilters
{
    public function __construct(
        public ?string $paymentStatus,
        public ?string $state,
        public ?DateTimeImmutable $from,
        public ?DateTimeImmutable $to,
        public ?int $createdBy,
        public int $page,
        public int $perPage,
    ) {}
}
