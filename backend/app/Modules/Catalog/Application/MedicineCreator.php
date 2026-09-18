<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

interface MedicineCreator
{
    public function create(CreateMedicineCommand $command): int;
}
