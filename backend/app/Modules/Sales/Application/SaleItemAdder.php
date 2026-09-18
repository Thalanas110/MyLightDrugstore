<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

interface SaleItemAdder
{
    public function add(AddSaleItemCommand $command): SaleDetails;
}
