<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

interface SalePaymentMarker
{
    public function markPaid(MarkSalePaidCommand $command): SaleDetails;
}
