<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Infrastructure\Persistence\Medicine;
use App\Modules\Inventory\Infrastructure\Persistence\InventoryLot;
use App\Modules\Inventory\Infrastructure\Persistence\InventoryReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryLot>
 */
final class InventoryLotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_id' => InventoryReceipt::factory(),
            'medicine_id' => Medicine::factory(),
            'received_at' => now(),
            'expires_at' => now()->addYears(2)->toDateString(),
            'quantity_received' => 24,
            'quantity_remaining' => 24,
        ];
    }
}
