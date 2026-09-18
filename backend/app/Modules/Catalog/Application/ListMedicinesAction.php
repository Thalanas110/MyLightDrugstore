<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

final class ListMedicinesAction
{
    public function __construct(private readonly MedicineListQuery $medicineListQuery) {}

    public function execute(MedicineListFilters $filters): MedicineListPage
    {
        return $this->medicineListQuery->search($filters);
    }
}
