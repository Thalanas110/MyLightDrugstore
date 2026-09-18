<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

final class MarkSalePaidAction
{
    public function __construct(private readonly SalePaymentMarker $salePaymentMarker) {}

    public function execute(MarkSalePaidCommand $command): SaleDetails
    {
        return $this->salePaymentMarker->markPaid($command);
    }
}
