<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

interface SaleCreator
{
    public function create(CreateSaleCommand $command): SaleDetails;
}
