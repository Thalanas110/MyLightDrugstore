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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use LogicException;
use Tests\Support\EncryptedTransportRequestBuilder;
use Tests\Support\EncryptedTransportResponseReader;
use Tests\TestCase;

final class SaleItemRemovalEndpointTest extends TestCase
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

    public function test_it_removes_a_sale_line_restores_its_original_fefo_lots_and_replays_safely(): void
    {
        $actor = User::query()->firstOrFail();
        $medicine = Medicine::factory()->create(['unit_price' => '2.00']);
        $firstLotId = $this->createLot($actor, $medicine, 3, CarbonImmutable::now('UTC')->addMonths(2));
        $secondLotId = $this->createLot($actor, $medicine, 5, CarbonImmutable::now('UTC')->addMonths(3));

        $saleResponse = $this->createSale([
            'items' => [
                ['medicineId' => $medicine->getKey(), 'quantity' => 2],
            ],
        ], 'sale-remove-create-001')->assertCreated();
        $sale = json_decode($this->responseBody($saleResponse, 'POST', '/api/v1/sales'), true, 512, JSON_THROW_ON_ERROR)['data'];
        $saleId = $sale['id'];

        if (! is_int($saleId)) {
            throw new LogicException('The sale fixture was created without a valid identifier.');
        }

        $medicine->unit_price = '3.50';
        $medicine->save();
        $addPath = '/api/v1/sales/'.$saleId.'/items';
        $addResponse = $this->addItem($addPath, [
            'medicineId' => $medicine->getKey(),
            'quantity' => 2,
        ], 'sale-remove-add-001')->assertCreated();
        $addedSale = json_decode($this->responseBody($addResponse, 'POST', $addPath), true, 512, JSON_THROW_ON_ERROR)['data'];
        $saleItemId = $addedSale['items'][1]['id'];

        if (! is_int($saleItemId)) {
            throw new LogicException('The sale item fixture was created without a valid identifier.');
        }

        $path = '/api/v1/sales/'.$saleId.'/items/'.$saleItemId;
        $response = $this->removeItem($path)->assertOk();
        $payload = json_decode($this->responseBody($response, 'DELETE', $path), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('4.00', $payload['data']['total']);
        $this->assertSame('active', $payload['data']['items'][0]['state']);
        $this->assertSame('removed', $payload['data']['items'][1]['state']);
        $this->assertSame('3.50', $payload['data']['items'][1]['unitPrice']);
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'total' => '4.00']);
        $this->assertDatabaseHas('sale_items', ['id' => $saleItemId, 'state' => 'removed']);
        $this->assertDatabaseHas('inventory_lots', ['id' => $firstLotId, 'quantity_remaining' => 1]);
        $this->assertDatabaseHas('inventory_lots', ['id' => $secondLotId, 'quantity_remaining' => 5]);
        $this->assertDatabaseHas('inventory_movements', [
            'sale_item_id' => $saleItemId,
            'inventory_lot_id' => $firstLotId,
            'movement_type' => 'sale_item_removal',
            'quantity_delta' => 1,
            'source_type' => 'sale',
            'source_id' => $saleId,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'sale_item_id' => $saleItemId,
            'inventory_lot_id' => $secondLotId,
            'movement_type' => 'sale_item_removal',
            'quantity_delta' => 1,
            'source_type' => 'sale',
            'source_id' => $saleId,
        ]);

        $retryResponse = $this->removeItem($path)->assertOk();

        $this->assertSame($payload, json_decode(
            $this->responseBody($retryResponse, 'DELETE', $path),
            true,
            512,
            JSON_THROW_ON_ERROR,
        ));
        $this->assertDatabaseCount('inventory_movements', 5);
        $this->assertDatabaseHas('inventory_lots', ['id' => $secondLotId, 'quantity_remaining' => 5]);
    }

    private function createUser(): User
    {
        $user = new User;
        $user->setUsername(new Username('sales.remove.staff'));
        $user->setFullName(new FullName('Sales Remove Staff'));
        $user->password = 'CorrectHorseBatteryStaple!2026';
        $user->role = StaffRole::Staff;
        $user->active = true;
        $user->save();

        return $user;
    }

    private function createLot(User $actor, Medicine $medicine, int $quantity, CarbonImmutable $expiresAt): int
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
            'expires_at' => $expiresAt,
            'quantity_received' => $quantity,
            'quantity_remaining' => $quantity,
        ]);
        $lotId = $lot->getKey();

        if (! is_int($lotId)) {
            throw new LogicException('The inventory lot fixture has an invalid identifier.');
        }

        return $lotId;
    }

    /** @param array<string, array<int, array<string, int>>> $payload */
    private function createSale(array $payload, string $idempotencyKey): TestResponse
    {
        $path = '/api/v1/sales';
        $key = (new EncryptedTransportRequestBuilder)->build('POST', $path, $payload);
        $this->lastTransportKey = $key['aesKey'];

        return $this->call('POST', $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => 'test-csrf-token',
            'HTTP_IDEMPOTENCY_KEY' => $idempotencyKey,
            'HTTP_X_TRANSPORT_KEY' => $key['header'],
        ], json_encode($key['envelope'], JSON_THROW_ON_ERROR));
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

    private function removeItem(string $path): TestResponse
    {
        $key = (new EncryptedTransportRequestBuilder)->buildBodyless();
        $this->lastTransportKey = $key['aesKey'];

        return $this->call('DELETE', $path, [], [], [], [
            'HTTP_X_CSRF_TOKEN' => 'test-csrf-token',
            'HTTP_X_TRANSPORT_KEY' => $key['header'],
        ]);
    }

    private function responseBody(TestResponse $response, string $method, string $path): string
    {
        return (new EncryptedTransportResponseReader)
            ->read($response, $this->lastTransportKey, $method, $path)['body'];
    }
}
