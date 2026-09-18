<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

final class ReceiveStockAction
{
    public function __construct(private readonly InventoryReceiptWriter $receiptWriter) {}

    public function execute(ReceiveStockCommand $command): InventoryReceiptResult
    {
        return $this->receiptWriter->receive($command);
    }
}
