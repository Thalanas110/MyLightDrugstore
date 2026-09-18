<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

interface SaleListQuery
{
    public function search(SaleListFilters $filters): SaleListPage;
}
