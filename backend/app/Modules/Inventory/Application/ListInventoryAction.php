<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

final class ListInventoryAction
{
    public function __construct(private readonly InventorySummaryQuery $inventorySummaryQuery) {}

    public function execute(InventorySummaryFilters $filters): InventorySummaryPage
    {
        return $this->inventorySummaryQuery->search($filters);
    }
}
