<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

final class RemoveSaleItemAction
{
    public function __construct(private readonly SaleItemRemover $saleItemRemover) {}

    public function execute(RemoveSaleItemCommand $command): SaleDetails
    {
        return $this->saleItemRemover->remove($command);
    }
}
