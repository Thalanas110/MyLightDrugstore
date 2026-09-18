<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

final class AddSaleItemAction
{
    public function __construct(private readonly SaleItemAdder $saleItemAdder) {}

    public function execute(AddSaleItemCommand $command): SaleDetails
    {
        return $this->saleItemAdder->add($command);
    }
}
