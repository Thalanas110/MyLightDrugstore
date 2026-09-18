<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

final class UpdateMedicineAction
{
    public function __construct(private readonly MedicineUpdater $medicineUpdater) {}

    public function execute(UpdateMedicineCommand $command): void
    {
        $this->medicineUpdater->update($command);
    }
}
