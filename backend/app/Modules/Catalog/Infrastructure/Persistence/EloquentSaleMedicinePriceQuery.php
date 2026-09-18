<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence;

use App\Modules\Catalog\Application\SaleMedicinePriceQuery;
use LogicException;

final class EloquentSaleMedicinePriceQuery implements SaleMedicinePriceQuery
{
    public function findActivePrices(array $medicineIds): array
    {
        $prices = [];

        foreach (Medicine::query()
            ->whereKey($medicineIds)
            ->where('active', true)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'unit_price']) as $medicine) {
            $medicineId = $medicine->getKey();
            $unitPrice = $medicine->getAttribute('unit_price');

            if (! is_int($medicineId) || ! is_string($unitPrice)) {
                throw new LogicException('The active medicine price query returned invalid sale data.');
            }

            $prices[$medicineId] = $unitPrice;
        }

        return $prices;
    }
}
