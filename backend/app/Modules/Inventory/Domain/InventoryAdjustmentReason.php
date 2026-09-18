<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain;

enum InventoryAdjustmentReason: string
{
    case StockCount = 'stock_count';
    case Damage = 'damage';
    case Expiry = 'expiry';
    case Correction = 'correction';
}
