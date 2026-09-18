<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

final readonly class ReserveStockForSaleCommand
{
    /**
     * @param  list<ReserveSaleStockItem>  $items
     */
    public function __construct(
        public int $actorUserId,
        public int $saleId,
        public array $items,
    ) {}
}
