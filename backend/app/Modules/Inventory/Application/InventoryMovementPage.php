<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

final readonly class InventoryMovementPage
{
    /** @param list<InventoryMovementItem> $items */
    public function __construct(
        public array $items,
        public int $page,
        public int $perPage,
        public int $total,
    ) {}
}
