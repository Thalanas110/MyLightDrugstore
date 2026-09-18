<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

final class CreateSaleAction
{
    public function __construct(private readonly SaleCreator $saleCreator) {}

    public function execute(CreateSaleCommand $command): SaleDetails
    {
        return $this->saleCreator->create($command);
    }
}
