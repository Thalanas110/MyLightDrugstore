<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

interface SaleMedicinePriceQuery
{
    /**
     * @param  list<int>  $medicineIds
     * @return array<int, string>
     */
    public function findActivePrices(array $medicineIds): array;
}
