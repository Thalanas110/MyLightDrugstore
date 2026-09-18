<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

interface MedicineListQuery
{
    public function search(MedicineListFilters $filters): MedicineListPage;
}
