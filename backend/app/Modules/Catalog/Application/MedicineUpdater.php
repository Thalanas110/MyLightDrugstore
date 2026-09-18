<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

interface MedicineUpdater
{
    public function update(UpdateMedicineCommand $command): void;
}
