<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

final class ListInventoryMovementsAction
{
    public function __construct(private readonly InventoryMovementQuery $inventoryMovementQuery) {}

    public function execute(InventoryMovementFilters $filters): InventoryMovementPage
    {
        return $this->inventoryMovementQuery->search($filters);
    }
}
