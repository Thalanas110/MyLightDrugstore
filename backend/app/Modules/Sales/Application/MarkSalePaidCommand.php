<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

final readonly class MarkSalePaidCommand
{
    public function __construct(public int $saleId) {}
}
