<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Catalog\Infrastructure\Persistence\Medicine;
use App\Modules\Identity\Domain\Users\FullName;
use App\Modules\Identity\Domain\Users\StaffRole;
use App\Modules\Identity\Domain\Users\Username;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Inventory\Infrastructure\Persistence\InventoryLot;
use App\Modules\Inventory\Infrastructure\Persistence\InventoryMovement;
use App\Modules\Inventory\Infrastructure\Persistence\InventoryReceipt;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\Support\EncryptedTransportRequestBuilder;
use Tests\Support\EncryptedTransportResponseReader;
use Tests\TestCase;

final class InventoryAdjustmentEndpointTest extends TestCase
{
    use RefreshDatabase;

    private string $lastTransportKey = '';

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
        $this->actingAs($this->createUser(), 'web');
        $this->withSession(['_token' => 'test-csrf-token']);
    }

    public function test_it_records_actor_reason_and_signed_movements_for_lot_adjustments(): void
    {
        $actor = User::query()->firstOrFail();
        $firstLot = $this->createLot(20);
        $secondLot = $this->createLot(7);

        $response = $this->adjust([
            'reason' => 'stock_count',
            'items' => [
                ['lotId' => $firstLot->getKey(), 'quantityDelta' => 3],
                ['lotId' => $secondLot->getKey(), 'quantityDelta' => -2],
            ],
        ])->assertCreated();
        $payload = json_decode($this->responseBody($response), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('stock_count', $payload['data']['reason']);
        $this->assertSame(2, $payload['data']['itemCount']);
        $this->assertSame(1, $payload['data']['totalQuantityDelta']);
        $this->assertDatabaseCount('inventory_adjustments', 1);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertDatabaseHas('inventory_lots', ['id' => $firstLot->getKey(), 'quantity_remaining' => 23]);
        $this->assertDatabaseHas('inventory_lots', ['id' => $secondLot->getKey(), 'quantity_remaining' => 5]);

        $adjustment = DB::table('inventory_adjustments')->first();
        $this->assertNotNull($adjustment);
        $this->assertSame($actor->getKey(), $adjustment->actor_user_id);
        $this->assertSame('stock_count', $adjustment->reason);
        $this->assertSame([
            [-2, $secondLot->getKey()],
            [3, $firstLot->getKey()],
        ], InventoryMovement::query()
            ->orderBy('quantity_delta')
            ->get(['quantity_delta', 'inventory_lot_id'])
            ->map(static fn (InventoryMovement $movement): array => [$movement->quantity_delta, $movement->inventory_lot_id])
            ->all());
        $this->assertSame(
            [$adjustment->id],
            InventoryMovement::query()->distinct()->where('source_type', 'inventory_adjustment')->pluck('source_id')->all(),
        );
    }

    public function test_an_adjustment_that_would_make_any_lot_negative_changes_nothing(): void
    {
        $firstLot = $this->createLot(1);
        $secondLot = $this->createLot(4);

        $response = $this->adjust([
            'reason' => 'damage',
            'items' => [
                ['lotId' => $firstLot->getKey(), 'quantityDelta' => -2],
                ['lotId' => $secondLot->getKey(), 'quantityDelta' => 2],
            ],
        ])->assertConflict();

        $payload = json_decode($this->responseBody($response), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('conflict', $payload['error']['code']);
        $this->assertDatabaseCount('inventory_adjustments', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseHas('inventory_lots', ['id' => $firstLot->getKey(), 'quantity_remaining' => 1]);
        $this->assertDatabaseHas('inventory_lots', ['id' => $secondLot->getKey(), 'quantity_remaining' => 4]);
    }

    public function test_invalid_or_duplicate_lot_adjustment_items_are_rejected(): void
    {
        $lot = $this->createLot(5);

        $response = $this->adjust([
            'reason' => 'untracked_reason',
            'items' => [
                ['lotId' => $lot->getKey(), 'quantityDelta' => 0],
                ['lotId' => $lot->getKey(), 'quantityDelta' => 1],
            ],
        ])->assertUnprocessable();

        $payload = json_decode($this->responseBody($response), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('validation_failed', $payload['error']['code']);
        $this->assertArrayHasKey('reason', $payload['error']['details']);
        $this->assertArrayHasKey('items.0.quantityDelta', $payload['error']['details']);
        $this->assertArrayHasKey('items.1.lotId', $payload['error']['details']);
        $this->assertDatabaseCount('inventory_adjustments', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_a_movement_persistence_failure_rolls_back_all_adjustment_writes(): void
    {
        $firstLot = $this->createLot(5);
        $secondLot = $this->createLot(8);
        $movementInserts = 0;

        DB::listen(static function (QueryExecuted $query) use (&$movementInserts): void {
            if (str_starts_with(strtolower($query->sql), 'insert') && str_contains($query->sql, 'inventory_movements')) {
                $movementInserts++;

                if ($movementInserts === 2) {
                    throw new RuntimeException('Simulated movement persistence failure.');
                }
            }
        });

        $response = $this->adjust([
            'reason' => 'correction',
            'items' => [
                ['lotId' => $firstLot->getKey(), 'quantityDelta' => 1],
                ['lotId' => $secondLot->getKey(), 'quantityDelta' => -1],
            ],
        ])->assertInternalServerError();

        $payload = json_decode($this->responseBody($response), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('internal_error', $payload['error']['code']);
        $this->assertDatabaseCount('inventory_adjustments', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseHas('inventory_lots', ['id' => $firstLot->getKey(), 'quantity_remaining' => 5]);
        $this->assertDatabaseHas('inventory_lots', ['id' => $secondLot->getKey(), 'quantity_remaining' => 8]);
    }

    private function createUser(): User
    {
        $user = new User;
        $user->setUsername(new Username('inventory.staff'));
        $user->setFullName(new FullName('Inventory Staff'));
        $user->password = 'CorrectHorseBatteryStaple!2026';
        $user->role = StaffRole::Staff;
        $user->active = true;
        $user->save();

        return $user;
    }

    private function createLot(int $quantity): InventoryLot
    {
        $user = User::query()->firstOrFail();
        $medicine = Medicine::factory()->create();
        $receipt = InventoryReceipt::query()->create([
            'actor_user_id' => $user->getKey(),
            'received_at' => '2026-09-18T02:00:00Z',
        ]);

        return InventoryLot::query()->create([
            'receipt_id' => $receipt->getKey(),
            'medicine_id' => $medicine->getKey(),
            'received_at' => '2026-09-18T02:00:00Z',
            'expires_at' => '2028-06-30',
            'quantity_received' => $quantity,
            'quantity_remaining' => $quantity,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function adjust(array $payload): TestResponse
    {
        $path = '/api/v1/inventory/adjustments';
        $key = (new EncryptedTransportRequestBuilder)->build('POST', $path, $payload);
        $this->lastTransportKey = $key['aesKey'];

        return $this->call('POST', $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => 'test-csrf-token',
            'HTTP_X_TRANSPORT_KEY' => $key['header'],
        ], json_encode($key['envelope'], JSON_THROW_ON_ERROR));
    }

    private function responseBody(TestResponse $response): string
    {
        return (new EncryptedTransportResponseReader)
            ->read($response, $this->lastTransportKey, 'POST', '/api/v1/inventory/adjustments')['body'];
    }
}
