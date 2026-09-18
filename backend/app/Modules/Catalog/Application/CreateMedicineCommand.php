<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

final readonly class CreateMedicineCommand
{
    public function __construct(
        public string $genericName,
        public ?string $brandName,
        public ?string $description,
        public string $dosageForm,
        public ?string $strength,
        public string $unitPrice,
        public ?string $storageLocation,
    ) {}
}
