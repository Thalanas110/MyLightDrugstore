<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

final readonly class MedicineListItem
{
    public function __construct(
        public int $id,
        public string $genericName,
        public ?string $brandName,
        public ?string $description,
        public string $dosageForm,
        public ?string $strength,
        public string $unitPrice,
        public ?string $storageLocation,
        public bool $active,
        public int $stockOnHand,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}
}
