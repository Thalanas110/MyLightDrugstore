<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Catalog\Infrastructure\Persistence\Medicine;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Inventory\Infrastructure\Persistence\InventoryLot;
use App\Modules\Inventory\Infrastructure\Persistence\InventoryMovement;
use App\Modules\Inventory\Infrastructure\Persistence\InventoryReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

final class StockLedgerPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('data_protection.keys', [
            'current' => [
                'key_id' => 'test-data-2026-01',
                'key_base64' => base64_encode(str_repeat('d', 32)),
            ],
            'retiring' => [],
        ]);
        config()->set('data_protection.username_lookup_key_base64', base64_encode(str_repeat('l', 32)));
    }

    public function test_receipts_lots_and_movements_preserve_their_actor_and_medicine_relationships(): void
    {
        $actor = User::factory()->create();
        $medicine = Medicine::factory()->create();
        $receipt = InventoryReceipt::factory()->create([
            'actor_user_id' => $actor->getKey(),
            'received_at' => '2026-09-18 02:00:00',
        ]);
        $lot = InventoryLot::factory()->create([
            'receipt_id' => $receipt->getKey(),
            'medicine_id' => $medicine->getKey(),
            'quantity_received' => 24,
            'quantity_remaining' => 24,
            'expires_at' => '2028-06-30',
        ]);
        $movement = $this->createReceiptMovement($actor, $medicine, $lot, $receipt);

        $this->assertSame($actor->getKey(), $receipt->actor->getKey());
        $this->assertSame($receipt->getKey(), $lot->receipt->getKey());
        $this->assertSame($medicine->getKey(), $lot->medicine->getKey());
        $this->assertSame($lot->getKey(), $movement->lot->getKey());
        $this->assertSame($actor->getKey(), $movement->actor->getKey());
        $this->assertSame(24, $lot->quantity_remaining);
    }

    public function test_inventory_movements_cannot_be_updated(): void
    {
        $movement = $this->createMovementGraph();
        $movement->quantity_delta = 12;

        $this->expectException(LogicException::class);
        $movement->save();
    }

    public function test_inventory_movements_cannot_be_deleted(): void
    {
        $movement = $this->createMovementGraph();

        $this->expectException(LogicException::class);
        $movement->delete();
    }

    private function createMovementGraph(): InventoryMovement
    {
        $actor = User::factory()->create();
        $medicine = Medicine::factory()->create();
        $receipt = InventoryReceipt::factory()->create(['actor_user_id' => $actor->getKey()]);
        $lot = InventoryLot::factory()->create([
            'receipt_id' => $receipt->getKey(),
            'medicine_id' => $medicine->getKey(),
        ]);

        return $this->createReceiptMovement($actor, $medicine, $lot, $receipt);
    }

    private function createReceiptMovement(
        User $actor,
        Medicine $medicine,
        InventoryLot $lot,
        InventoryReceipt $receipt,
    ): InventoryMovement {
        return InventoryMovement::query()->create([
            'medicine_id' => $medicine->getKey(),
            'inventory_lot_id' => $lot->getKey(),
            'actor_user_id' => $actor->getKey(),
            'movement_type' => 'receipt',
            'quantity_delta' => 24,
            'source_type' => 'inventory_receipt',
            'source_id' => $receipt->getKey(),
            'occurred_at' => '2026-09-18 02:00:00',
        ]);
    }
}
