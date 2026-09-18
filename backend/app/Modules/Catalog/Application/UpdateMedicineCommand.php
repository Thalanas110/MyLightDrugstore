<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

final readonly class UpdateMedicineCommand
{
    /** @param array<string, string|null> $changes */
    public function __construct(
        public int $medicineId,
        public array $changes,
    ) {}
}
