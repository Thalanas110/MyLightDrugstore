<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

interface InventorySummaryQuery
{
    public function search(InventorySummaryFilters $filters): InventorySummaryPage;
}
