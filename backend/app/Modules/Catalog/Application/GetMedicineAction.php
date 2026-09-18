<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

final class GetMedicineAction
{
    public function __construct(private readonly MedicineListQuery $medicineListQuery) {}

    public function execute(int $medicineId): ?MedicineListItem
    {
        return $this->medicineListQuery->find($medicineId);
    }
}
