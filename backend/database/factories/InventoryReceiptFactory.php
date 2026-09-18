<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Inventory\Infrastructure\Persistence\InventoryReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryReceipt>
 */
final class InventoryReceiptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_user_id' => User::factory(),
            'received_at' => now(),
        ];
    }
}
