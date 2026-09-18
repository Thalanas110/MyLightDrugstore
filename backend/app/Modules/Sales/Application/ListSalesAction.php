<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

final class ListSalesAction
{
    public function __construct(private readonly SaleListQuery $saleListQuery) {}

    public function execute(SaleListFilters $filters): SaleListPage
    {
        return $this->saleListQuery->search($filters);
    }
}
