<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

final class ListInventoryLotsAction
{
    public function __construct(private readonly InventoryLotQuery $inventoryLotQuery) {}

    public function execute(InventoryLotFilters $filters): InventoryLotPage
    {
        return $this->inventoryLotQuery->search($filters);
    }
}
