<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

final readonly class CreateSaleCommand
{
    /**
     * @param  list<CreateSaleItem>  $items
     */
    public function __construct(
        public int $actorUserId,
        public string $idempotencyKey,
        public array $items,
    ) {}
}
