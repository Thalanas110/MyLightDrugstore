<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

interface InventoryLotQuery
{
    public function search(InventoryLotFilters $filters): InventoryLotPage;
}
