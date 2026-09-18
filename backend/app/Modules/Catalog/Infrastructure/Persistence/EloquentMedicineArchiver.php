<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence;

use App\Modules\Catalog\Application\MedicineArchiver;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EloquentMedicineArchiver implements MedicineArchiver
{
    public function archive(int $medicineId): void
    {
        $medicine = Medicine::query()->find($medicineId);

        if ($medicine === null) {
            throw (new ModelNotFoundException)->setModel(Medicine::class, [$medicineId]);
        }

        if (! $medicine->active) {
            return;
        }

        $medicine->active = false;
        $medicine->save();
    }
}
