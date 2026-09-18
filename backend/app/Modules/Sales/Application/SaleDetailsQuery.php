<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

interface SaleDetailsQuery
{
    public function find(int $saleId): ?SaleDetails;
}
