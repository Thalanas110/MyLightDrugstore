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
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use LogicException;
use RuntimeException;
use Tests\Support\EncryptedTransportRequestBuilder;
use Tests\Support\EncryptedTransportResponseReader;
use Tests\TestCase;

final class SalesCreateEndpointTest extends TestCase
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

        $user = $this->createUser();
        $this->actingAs($user, 'web');
        $this->withSession(['_token' => 'test-csrf-token']);
    }

    public function test_it_creates_an_open_sale_using_server_prices_and_fefo_lots(): void
    {
        $actor = User::query()->firstOrFail();
        $medicine = Medicine::factory()->create(['unit_price' => '12.35']);
        $now = CarbonImmutable::now('UTC')->startOfDay();
        $firstLotId = $this->createLot($actor, $medicine, $now->addMonths(2), 1);
        $secondLotId = $this->createLot($actor, $medicine, $now->addMonths(5), 4);

        $response = $this->createSale([
            'items' => [
                ['medicineId' => $medicine->getKey(), 'quantity' => 3],
            ],
        ], 'sale-create-fefo-001')->assertCreated();
        $payload = json_decode($this->responseBody($response), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('open', $payload['data']['state']);
        $this->assertSame('unpaid', $payload['data']['paymentStatus']);
        $this->assertSame('37.05', $payload['data']['total']);
        $this->assertSame('12.35', $payload['data']['items'][0]['unitPrice']);
        $this->assertSame(3, $payload['data']['items'][0]['quantity']);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseHas('sales', [
            'id' => $payload['data']['id'],
            'created_by_user_id' => $actor->getKey(),
            'state' => 'open',
            'payment_status' => 'unpaid',
            'total' => '37.05',
        ]);
        $this->assertDatabaseHas('inventory_lots', ['id' => $firstLotId, 'quantity_remaining' => 0]);
        $this->assertDatabaseHas('inventory_lots', ['id' => $secondLotId, 'quantity_remaining' => 2]);
        $this->assertSame([-2, -1], DB::table('inventory_movements')
            ->where('movement_type', 'sale')
            ->orderBy('quantity_delta')
            ->pluck('quantity_delta')
            ->all());
        $this->assertSame(
            [$payload['data']['id']],
            DB::table('inventory_movements')->where('source_type', 'sale')->distinct()->pluck('source_id')->all(),
        );
    }

    public function test_it_rejects_insufficient_stock_without_creating_a_sale_or_movement(): void
    {
        $actor = User::query()->firstOrFail();
        $availableMedicine = Medicine::factory()->create();
        $unavailableMedicine = Medicine::factory()->create();
        $availableLotId = $this->createLot($actor, $availableMedicine, CarbonImmutable::now('UTC')->addMonths(2), 5);
        $unavailableLotId = $this->createLot($actor, $unavailableMedicine, CarbonImmutable::now('UTC')->addMonths(3), 2);
        $expiredLotId = $this->createLot($actor, $unavailableMedicine, CarbonImmutable::now('UTC')->subDay(), 10);

        $response = $this->createSale([
            'items' => [
                ['medicineId' => $availableMedicine->getKey(), 'quantity' => 3],
                ['medicineId' => $unavailableMedicine->getKey(), 'quantity' => 3],
            ],
        ], 'sale-create-stock-001')->assertConflict();
        $payload = json_decode($this->responseBody($response), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('insufficient_stock', $payload['error']['code']);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseHas('inventory_lots', ['id' => $availableLotId, 'quantity_remaining' => 5]);
        $this->assertDatabaseHas('inventory_lots', ['id' => $unavailableLotId, 'quantity_remaining' => 2]);
        $this->assertDatabaseHas('inventory_lots', ['id' => $expiredLotId, 'quantity_remaining' => 10]);
        $this->assertDatabaseCount('sales_idempotency_keys', 0);
    }

    public function test_a_repeated_idempotency_key_returns_the_original_sale_without_reserving_twice(): void
    {
        $actor = User::query()->firstOrFail();
        $medicine = Medicine::factory()->create();
        $lotId = $this->createLot($actor, $medicine, CarbonImmutable::now('UTC')->addMonths(2), 5);
        $request = [
            'items' => [
                ['medicineId' => $medicine->getKey(), 'quantity' => 2],
            ],
        ];

        $firstResponse = $this->createSale($request, 'sale-create-repeat-001')->assertCreated();
        $firstBody = $this->responseBody($firstResponse);
        $secondResponse = $this->createSale($request, 'sale-create-repeat-001')->assertCreated();

        $this->assertSame($firstBody, $this->responseBody($secondResponse));
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseHas('inventory_lots', ['id' => $lotId, 'quantity_remaining' => 3]);
    }

    public function test_reusing_an_idempotency_key_for_a_different_sale_request_is_rejected(): void
    {
        $actor = User::query()->firstOrFail();
        $medicine = Medicine::factory()->create();
        $this->createLot($actor, $medicine, CarbonImmutable::now('UTC')->addMonths(2), 5);
        $idempotencyKey = 'sale-create-reuse-001';

        $this->createSale([
            'items' => [
                ['medicineId' => $medicine->getKey(), 'quantity' => 1],
            ],
        ], $idempotencyKey)->assertCreated();

        $response = $this->createSale([
            'items' => [
                ['medicineId' => $medicine->getKey(), 'quantity' => 2],
            ],
        ], $idempotencyKey)->assertConflict();
        $payload = json_decode($this->responseBody($response), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('idempotency_key_reused', $payload['error']['code']);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_it_requires_an_idempotency_key(): void
    {
        $medicine = Medicine::factory()->create();

        $missingKey = $this->createSale([
            'items' => [
                ['medicineId' => $medicine->getKey(), 'quantity' => 1],
            ],
        ], '')->assertUnprocessable();
        $missingKeyBody = json_decode($this->responseBody($missingKey), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('validation_failed', $missingKeyBody['error']['code']);
        $this->assertArrayHasKey('idempotencyKey', $missingKeyBody['error']['details']);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_it_rejects_duplicate_medicine_lines(): void
    {
        $medicine = Medicine::factory()->create();

        $duplicateMedicine = $this->createSale([
            'items' => [
                ['medicineId' => $medicine->getKey(), 'quantity' => 1],
                ['medicineId' => $medicine->getKey(), 'quantity' => 2],
            ],
        ], 'sale-create-duplicate-001')->assertUnprocessable();
        $duplicateBody = json_decode($this->responseBody($duplicateMedicine), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('items.1.medicineId', $duplicateBody['error']['details']);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_it_preserves_cents_in_large_sale_totals_within_the_supported_limits(): void
    {
        $actor = User::query()->firstOrFail();
        $firstMedicine = Medicine::factory()->create(['unit_price' => '99999999.99']);
        $secondMedicine = Medicine::factory()->create(['unit_price' => '99999999.99']);
        $quantity = 99999;
        $this->createLot($actor, $firstMedicine, CarbonImmutable::now('UTC')->addMonths(2), $quantity);
        $this->createLot($actor, $secondMedicine, CarbonImmutable::now('UTC')->addMonths(2), $quantity);

        $response = $this->createSale([
            'items' => [
                ['medicineId' => $firstMedicine->getKey(), 'quantity' => $quantity],
                ['medicineId' => $secondMedicine->getKey(), 'quantity' => $quantity],
            ],
        ], 'sale-create-large-total-001')->assertCreated();
        $payload = json_decode($this->responseBody($response), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('19999799998000.02', $payload['data']['total']);
        $this->assertSame('9999899999000.01', $payload['data']['items'][0]['lineTotal']);
        $this->assertSame('9999899999000.01', $payload['data']['items'][1]['lineTotal']);
        $this->assertDatabaseHas('sales', ['id' => $payload['data']['id'], 'total' => '19999799998000.02']);
    }

    public function test_it_rejects_inactive_medicines_and_unrecognized_input_fields(): void
    {
        $inactiveMedicine = Medicine::factory()->create(['active' => false]);

        $inactiveResponse = $this->createSale([
            'items' => [
                ['medicineId' => $inactiveMedicine->getKey(), 'quantity' => 1],
            ],
        ], 'sale-create-inactive-001')->assertUnprocessable();
        $inactiveBody = json_decode($this->responseBody($inactiveResponse), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('items.0.medicineId', $inactiveBody['error']['details']);

        $medicine = Medicine::factory()->create();
        $unknownFieldsResponse = $this->createSale([
            'total' => '0.01',
            'items' => [
                ['medicineId' => $medicine->getKey(), 'quantity' => 1, 'unitPrice' => '0.01'],
            ],
        ], 'sale-create-unknown-fields-001')->assertUnprocessable();
        $unknownFieldsBody = json_decode($this->responseBody($unknownFieldsResponse), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('total', $unknownFieldsBody['error']['details']);
        $this->assertArrayHasKey('items.0', $unknownFieldsBody['error']['details']);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_a_movement_failure_rolls_back_the_sale_and_every_stock_change(): void
    {
        $actor = User::query()->firstOrFail();
        $firstMedicine = Medicine::factory()->create();
        $secondMedicine = Medicine::factory()->create();
        $firstLotId = $this->createLot($actor, $firstMedicine, CarbonImmutable::now('UTC')->addMonths(2), 3);
        $secondLotId = $this->createLot($actor, $secondMedicine, CarbonImmutable::now('UTC')->addMonths(2), 4);
        $movementInserts = 0;

        DB::listen(static function (QueryExecuted $query) use (&$movementInserts): void {
            if (str_starts_with(strtolower($query->sql), 'insert') && str_contains($query->sql, 'inventory_movements')) {
                $movementInserts++;

                if ($movementInserts === 2) {
                    throw new RuntimeException('Simulated sale movement persistence failure.');
                }
            }
        });

        $response = $this->createSale([
            'items' => [
                ['medicineId' => $firstMedicine->getKey(), 'quantity' => 1],
                ['medicineId' => $secondMedicine->getKey(), 'quantity' => 2],
            ],
        ], 'sale-create-rollback-001')->assertInternalServerError();
        $payload = json_decode($this->responseBody($response), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('internal_error', $payload['error']['code']);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('sales_idempotency_keys', 0);
        $this->assertDatabaseHas('inventory_lots', ['id' => $firstLotId, 'quantity_remaining' => 3]);
        $this->assertDatabaseHas('inventory_lots', ['id' => $secondLotId, 'quantity_remaining' => 4]);
    }

    private function createUser(): User
    {
        $user = new User;
        $user->setUsername(new Username('sales.staff'));
        $user->setFullName(new FullName('Sales Staff'));
        $user->password = 'CorrectHorseBatteryStaple!2026';
        $user->role = StaffRole::Staff;
        $user->active = true;
        $user->save();

        return $user;
    }

    private function createLot(User $actor, Medicine $medicine, CarbonImmutable $expiresAt, int $quantity): int
    {
        $receipt = InventoryReceipt::query()->create([
            'actor_user_id' => $actor->getKey(),
            'received_at' => CarbonImmutable::now('UTC'),
        ]);
        $receiptId = $receipt->getKey();

        if (! is_int($receiptId)) {
            throw new LogicException('The inventory receipt was created without a valid identifier.');
        }

        $lot = InventoryLot::query()->create([
            'receipt_id' => $receiptId,
            'medicine_id' => $medicine->getKey(),
            'received_at' => CarbonImmutable::now('UTC'),
            'expires_at' => $expiresAt,
            'quantity_received' => $quantity,
            'quantity_remaining' => $quantity,
        ]);
        $lotId = $lot->getKey();

        if (! is_int($lotId)) {
            throw new LogicException('The inventory lot was created without a valid identifier.');
        }

        return $lotId;
    }

    /** @param array<string, mixed> $payload */
    private function createSale(array $payload, string $idempotencyKey): TestResponse
    {
        $path = '/api/v1/sales';
        $key = (new EncryptedTransportRequestBuilder)->build('POST', $path, $payload);
        $this->lastTransportKey = $key['aesKey'];

        return $this->call('POST', $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_CSRF_TOKEN' => 'test-csrf-token',
            'HTTP_X_CSRF_TOKEN' => 'test-csrf-token',
            'HTTP_IDEMPOTENCY_KEY' => $idempotencyKey,
            'HTTP_X_TRANSPORT_KEY' => $key['header'],
        ], json_encode($key['envelope'], JSON_THROW_ON_ERROR));
    }

    private function responseBody(TestResponse $response): string
    {
        return (new EncryptedTransportResponseReader)
            ->read($response, $this->lastTransportKey, 'POST', '/api/v1/sales')['body'];
    }
}
