<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence;

use App\Modules\Catalog\Application\MedicineUpdater;
use App\Modules\Catalog\Application\UpdateMedicineCommand;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use LogicException;

final class EloquentMedicineUpdater implements MedicineUpdater
{
    private const array FIELD_COLUMNS = [
        'genericName' => 'generic_name',
        'brandName' => 'brand_name',
        'description' => 'description',
        'dosageForm' => 'dosage_form',
        'strength' => 'strength',
        'unitPrice' => 'unit_price',
        'storageLocation' => 'storage_location',
    ];

    public function update(UpdateMedicineCommand $command): void
    {
        if ($command->changes === []) {
            throw new LogicException('At least one medicine field must be updated.');
        }

        $medicine = Medicine::query()->find($command->medicineId);

        if ($medicine === null) {
            throw (new ModelNotFoundException)->setModel(Medicine::class, [$command->medicineId]);
        }

        $changes = [];

        foreach ($command->changes as $field => $value) {
            $column = self::FIELD_COLUMNS[$field] ?? null;

            if (! is_string($column)) {
                throw new LogicException('The medicine update contains an unsupported field.');
            }

            $changes[$column] = $value;
        }

        $medicine->fill($changes);
        $medicine->save();
    }
}
