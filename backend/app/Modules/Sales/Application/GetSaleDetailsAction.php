<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

final class GetSaleDetailsAction
{
    public function __construct(private readonly SaleDetailsQuery $saleDetailsQuery) {}

    public function execute(int $saleId): ?SaleDetails
    {
        return $this->saleDetailsQuery->find($saleId);
    }
}
