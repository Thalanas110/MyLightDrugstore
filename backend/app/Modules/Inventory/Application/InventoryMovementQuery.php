<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

interface InventoryMovementQuery
{
    public function search(InventoryMovementFilters $filters): InventoryMovementPage;
}
