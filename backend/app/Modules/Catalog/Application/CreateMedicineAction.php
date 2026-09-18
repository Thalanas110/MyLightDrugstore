<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

final class CreateMedicineAction
{
    public function __construct(private readonly MedicineCreator $medicineCreator) {}

    public function execute(CreateMedicineCommand $command): int
    {
        return $this->medicineCreator->create($command);
    }
}
