<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

final readonly class SaleListPage
{
    /**
     * @param  list<SaleListItem>  $items
     */
    public function __construct(
        public array $items,
        public int $page,
        public int $perPage,
        public int $total,
    ) {}
}
