<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

use DateTimeImmutable;

final readonly class ReceiveStockCommand
{
    /**
     * @param  list<ReceiveStockItem>  $items
     */
    public function __construct(
        public int $actorUserId,
        public DateTimeImmutable $receivedAt,
        public array $items,
    ) {}
}
