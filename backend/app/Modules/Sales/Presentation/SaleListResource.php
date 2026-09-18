<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation;

use App\Modules\Sales\Application\SaleListItem;

final readonly class SaleListResource
{
    public function __construct(private SaleListItem $item) {}

    /**
     * @return array{
     *     id: int,
     *     createdBy: int,
     *     createdAt: string,
     *     state: string,
     *     paymentStatus: string,
     *     total: string,
     *     itemCount: int
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->item->id,
            'createdBy' => $this->item->createdBy,
            'createdAt' => $this->item->createdAt,
            'state' => $this->item->state,
            'paymentStatus' => $this->item->paymentStatus,
            'total' => $this->item->total,
            'itemCount' => $this->item->itemCount,
        ];
    }
}
