<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Sales;

use App\Modules\Catalog\Infrastructure\Persistence\Medicine;
use App\Modules\Identity\Domain\Users\FullName;
use App\Modules\Identity\Domain\Users\StaffRole;
use App\Modules\Identity\Domain\Users\Username;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Inventory\Infrastructure\Persistence\InventoryLot;
use App\Modules\Inventory\Infrastructure\Persistence\InventoryReceipt;
use App\Modules\Sales\Infrastructure\Persistence\Sale;
use App\Modules\Sales\Infrastructure\Persistence\SaleItem;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use LogicException;
use Tests\Support\EncryptedTransportRequestBuilder;
use Tests\Support\EncryptedTransportResponseReader;
use Tests\TestCase;

final class SaleItemAddEndpointTest extends TestCase
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

    public function test_it_adds_a_new_price_snapshot_line_to_an_open_unpaid_sale(): void
    {
        $actor = User::query()->firstOrFail();
        $medicine = Medicine::factory()->create(['unit_price' => '3.50']);
        $sale = Sale::factory()->create([
            'created_by_user_id' => $actor->getKey(),
            'state' => 'open',
            'payment_status' => 'unpaid',
            'total' => '4.00',
        ]);
        SaleItem::factory()->create([
            'sale_id' => $sale->getKey(),
            'medicine_id' => $medicine->getKey(),
            'quantity' => 2,
            'unit_price' => '2.00',
            'line_total' => '4.00',
        ]);
        $lotId = $this->createLot($actor, $medicine, 10);
        $saleId = $sale->getKey();

        if (! is_int($saleId)) {
            throw new LogicException('The sale fixture was created without a valid identifier.');
        }

        $path = '/api/v1/sales/'.$saleId.'/items';
        $response = $this->addItem($path, [
            'medicineId' => $medicine->getKey(),
            'quantity' => 3,
        ], 'sale-item-add-duplicate-001')->assertCreated();
        $payload = json_decode($this->responseBody($response, $path), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($saleId, $payload['data']['id']);
        $this->assertSame($actor->getKey(), $payload['data']['createdBy']);
        $this->assertSame('14.50', $payload['data']['total']);
        $this->assertCount(2, $payload['data']['items']);
        $this->assertSame('2.00', $payload['data']['items'][0]['unitPrice']);
        $this->assertSame('3.50', $payload['data']['items'][1]['unitPrice']);
        $this->assertSame(3, $payload['data']['items'][1]['quantity']);
        $this->assertSame('10.50', $payload['data']['items'][1]['lineTotal']);
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'total' => '14.50']);
        $this->assertDatabaseCount('sale_items', 2);
        $this->assertDatabaseHas('inventory_lots', ['id' => $lotId, 'quantity_remaining' => 7]);
        $this->assertDatabaseHas('inventory_movements', [
            'medicine_id' => $medicine->getKey(),
            'sale_item_id' => $payload['data']['items'][1]['id'],
            'movement_type' => 'sale',
            'quantity_delta' => -3,
            'source_type' => 'sale',
            'source_id' => $saleId,
        ]);
    }

    public function test_a_repeated_idempotency_key_replays_the_original_add_without_reserving_twice(): void
    {
        $actor = User::query()->firstOrFail();
        $medicine = Medicine::factory()->create(['unit_price' => '3.50']);
        $sale = Sale::factory()->create([
            'created_by_user_id' => $actor->getKey(),
            'state' => 'open',
            'payment_status' => 'unpaid',
            'total' => '0.00',
        ]);
        $lotId = $this->createLot($actor, $medicine, 5);
        $saleId = $sale->getKey();

        if (! is_int($saleId)) {
            throw new LogicException('The sale fixture was created without a valid identifier.');
        }

        $path = '/api/v1/sales/'.$saleId.'/items';
        $payload = ['medicineId' => $medicine->getKey(), 'quantity' => 2];
        $firstResponse = $this->addItem($path, $payload, 'sale-item-idempotency-001')->assertCreated();
        $firstBody = $this->responseBody($firstResponse, $path);
        $secondResponse = $this->addItem($path, $payload, 'sale-item-idempotency-001')->assertCreated();

        $this->assertSame($firstBody, $this->responseBody($secondResponse, $path));
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseHas('inventory_lots', ['id' => $lotId, 'quantity_remaining' => 3]);
    }

    public function test_reusing_an_add_item_key_for_a_different_request_is_rejected(): void
    {
        $actor = User::query()->firstOrFail();
        $medicine = Medicine::factory()->create(['unit_price' => '3.50']);
        $sale = Sale::factory()->create([
            'created_by_user_id' => $actor->getKey(),
            'state' => 'open',
            'payment_status' => 'unpaid',
            'total' => '0.00',
        ]);
        $this->createLot($actor, $medicine, 5);
        $saleId = $sale->getKey();

        if (! is_int($saleId)) {
            throw new LogicException('The sale fixture was created without a valid identifier.');
        }

        $path = '/api/v1/sales/'.$saleId.'/items';
        $this->addItem($path, [
            'medicineId' => $medicine->getKey(),
            'quantity' => 1,
        ], 'sale-item-idempotency-reuse-001')->assertCreated();
        $response = $this->addItem($path, [
            'medicineId' => $medicine->getKey(),
            'quantity' => 2,
        ], 'sale-item-idempotency-reuse-001')->assertConflict();
        $payload = json_decode($this->responseBody($response, $path), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('idempotency_key_reused', $payload['error']['code']);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_it_rejects_adding_to_a_paid_sale_without_changing_it(): void
    {
        $actor = User::query()->firstOrFail();
        $medicine = Medicine::factory()->create(['unit_price' => '3.50']);
        $sale = Sale::factory()->create([
            'created_by_user_id' => $actor->getKey(),
            'state' => 'open',
            'payment_status' => 'paid',
            'total' => '0.00',
        ]);
        $lotId = $this->createLot($actor, $medicine, 5);
        $saleId = $sale->getKey();

        if (! is_int($saleId)) {
            throw new LogicException('The sale fixture was created without a valid identifier.');
        }

        $path = '/api/v1/sales/'.$saleId.'/items';
        $response = $this->addItem($path, [
            'medicineId' => $medicine->getKey(),
            'quantity' => 1,
        ], 'sale-item-paid-sale-001')->assertConflict();
        $payload = json_decode($this->responseBody($response, $path), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('conflict', $payload['error']['code']);
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'payment_status' => 'paid', 'total' => '0.00']);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseHas('inventory_lots', ['id' => $lotId, 'quantity_remaining' => 5]);
    }

    public function test_it_rolls_back_a_sale_item_when_eligible_stock_is_insufficient(): void
    {
        $actor = User::query()->firstOrFail();
        $medicine = Medicine::factory()->create(['unit_price' => '3.50']);
        $sale = Sale::factory()->create([
            'created_by_user_id' => $actor->getKey(),
            'state' => 'open',
            'payment_status' => 'unpaid',
            'total' => '0.00',
        ]);
        $lotId = $this->createLot($actor, $medicine, 1);
        $saleId = $sale->getKey();

        if (! is_int($saleId)) {
            throw new LogicException('The sale fixture was created without a valid identifier.');
        }

        $path = '/api/v1/sales/'.$saleId.'/items';
        $response = $this->addItem($path, [
            'medicineId' => $medicine->getKey(),
            'quantity' => 2,
        ], 'sale-item-insufficient-stock-001')->assertConflict();
        $payload = json_decode($this->responseBody($response, $path), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('insufficient_stock', $payload['error']['code']);
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'total' => '0.00']);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseHas('inventory_lots', ['id' => $lotId, 'quantity_remaining' => 1]);
        $this->assertDatabaseCount('sales_idempotency_keys', 0);
    }

    private function createUser(): User
    {
        $user = new User;
        $user->setUsername(new Username('sales.item.staff'));
        $user->setFullName(new FullName('Sales Item Staff'));
        $user->password = 'CorrectHorseBatteryStaple!2026';
        $user->role = StaffRole::Staff;
        $user->active = true;
        $user->save();

        return $user;
    }

    private function createLot(User $actor, Medicine $medicine, int $quantity): int
    {
        $receivedAt = CarbonImmutable::now('UTC');
        $receipt = InventoryReceipt::query()->create([
            'actor_user_id' => $actor->getKey(),
            'received_at' => $receivedAt,
        ]);
        $receiptId = $receipt->getKey();

        if (! is_int($receiptId)) {
            throw new LogicException('The inventory receipt fixture has an invalid identifier.');
        }

        $lot = InventoryLot::query()->create([
            'receipt_id' => $receiptId,
            'medicine_id' => $medicine->getKey(),
            'received_at' => $receivedAt,
            'expires_at' => $receivedAt->addMonths(3),
            'quantity_received' => $quantity,
            'quantity_remaining' => $quantity,
        ]);
        $lotId = $lot->getKey();

        if (! is_int($lotId)) {
            throw new LogicException('The inventory lot fixture has an invalid identifier.');
        }

        return $lotId;
    }

    /** @param array<string, int> $payload */
    private function addItem(string $path, array $payload, string $idempotencyKey): TestResponse
    {
        $key = (new EncryptedTransportRequestBuilder)->build('POST', $path, $payload);
        $this->lastTransportKey = $key['aesKey'];

        return $this->call('POST', $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => 'test-csrf-token',
            'HTTP_IDEMPOTENCY_KEY' => $idempotencyKey,
            'HTTP_X_TRANSPORT_KEY' => $key['header'],
        ], json_encode($key['envelope'], JSON_THROW_ON_ERROR));
    }

    private function responseBody(TestResponse $response, string $path): string
    {
        return (new EncryptedTransportResponseReader)
            ->read($response, $this->lastTransportKey, 'POST', $path)['body'];
    }
}
