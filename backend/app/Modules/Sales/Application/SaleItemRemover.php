<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

interface SaleItemRemover
{
    public function remove(RemoveSaleItemCommand $command): SaleDetails;
}
