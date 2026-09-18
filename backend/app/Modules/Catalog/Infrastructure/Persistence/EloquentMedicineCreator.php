<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence;

use App\Modules\Catalog\Application\CreateMedicineCommand;
use App\Modules\Catalog\Application\MedicineCreator;
use LogicException;

final class EloquentMedicineCreator implements MedicineCreator
{
    public function create(CreateMedicineCommand $command): int
    {
        $medicine = Medicine::query()->create([
            'generic_name' => $command->genericName,
            'brand_name' => $command->brandName,
            'description' => $command->description,
            'dosage_form' => $command->dosageForm,
            'strength' => $command->strength,
            'unit_price' => $command->unitPrice,
            'storage_location' => $command->storageLocation,
            'active' => true,
        ]);
        $id = $medicine->getKey();

        if (is_int($id)) {
            return $id;
        }

        if (is_string($id) && ctype_digit($id) && (int) $id > 0) {
            return (int) $id;
        }

        throw new LogicException('The medicine was created without a valid identifier.');
    }
}
