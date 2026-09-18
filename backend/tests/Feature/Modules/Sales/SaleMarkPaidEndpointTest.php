<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Sales;

use App\Modules\Catalog\Infrastructure\Persistence\Medicine;
use App\Modules\Identity\Domain\Users\FullName;
use App\Modules\Identity\Domain\Users\StaffRole;
use App\Modules\Identity\Domain\Users\Username;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Inventory\Infrastructure\Persistence\InventoryLot;
use App\Modules\Inventory\Infrastructure\Persistence\InventoryMovement;
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

final class SaleMarkPaidEndpointTest extends TestCase
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

    public function test_it_marks_an_open_sale_paid_once_without_changing_inventory(): void
    {
        [$sale, $lotId] = $this->createSaleFixture();
        $saleId = $sale->getKey();

        if (! is_int($saleId)) {
            throw new LogicException('The sale fixture was created without a valid identifier.');
        }

        $path = '/api/v1/sales/'.$saleId.'/mark-paid';
        $response = $this->markPaid($path)->assertOk();
        $payload = json_decode($this->responseBody($response, $path), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('completed', $payload['data']['state']);
        $this->assertSame('paid', $payload['data']['paymentStatus']);
        $this->assertSame('8.00', $payload['data']['total']);
        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'state' => 'completed',
            'payment_status' => 'paid',
            'total' => '8.00',
        ]);
        $this->assertDatabaseHas('inventory_lots', ['id' => $lotId, 'quantity_remaining' => 3]);
        $this->assertDatabaseCount('inventory_movements', 1);

        $retryResponse = $this->markPaid($path)->assertOk();

        $this->assertSame($payload, json_decode(
            $this->responseBody($retryResponse, $path),
            true,
            512,
            JSON_THROW_ON_ERROR,
        ));
        $this->assertDatabaseHas('inventory_lots', ['id' => $lotId, 'quantity_remaining' => 3]);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_it_rejects_marking_a_cancelled_sale_paid(): void
    {
        [$sale] = $this->createSaleFixture();
        $sale->setAttribute('state', 'cancelled');
        $sale->save();
        $saleId = $sale->getKey();

        if (! is_int($saleId)) {
            throw new LogicException('The sale fixture was created without a valid identifier.');
        }

        $path = '/api/v1/sales/'.$saleId.'/mark-paid';
        $response = $this->markPaid($path)->assertConflict();
        $payload = json_decode($this->responseBody($response, $path), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('conflict', $payload['error']['code']);
        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'state' => 'cancelled',
            'payment_status' => 'unpaid',
        ]);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    private function createUser(): User
    {
        $user = new User;
        $user->setUsername(new Username('sales.payment.staff'));
        $user->setFullName(new FullName('Sales Payment Staff'));
        $user->password = 'CorrectHorseBatteryStaple!2026';
        $user->role = StaffRole::Staff;
        $user->active = true;
        $user->save();

        return $user;
    }

    /** @return array{Sale, int} */
    private function createSaleFixture(): array
    {
        $actor = User::query()->firstOrFail();
        $medicine = Medicine::factory()->create(['unit_price' => '4.00']);
        $sale = Sale::factory()->create([
            'created_by_user_id' => $actor->getKey(),
            'state' => 'open',
            'payment_status' => 'unpaid',
            'total' => '8.00',
        ]);
        $saleId = $sale->getKey();

        if (! is_int($saleId)) {
            throw new LogicException('The sale fixture was created without a valid identifier.');
        }

        $saleItem = SaleItem::factory()->create([
            'sale_id' => $saleId,
            'medicine_id' => $medicine->getKey(),
            'quantity' => 2,
            'unit_price' => '4.00',
            'line_total' => '8.00',
        ]);
        $saleItemId = $saleItem->getKey();

        if (! is_int($saleItemId)) {
            throw new LogicException('The sale item fixture was created without a valid identifier.');
        }

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
            'quantity_received' => 5,
            'quantity_remaining' => 3,
        ]);
        $lotId = $lot->getKey();

        if (! is_int($lotId)) {
            throw new LogicException('The inventory lot fixture has an invalid identifier.');
        }

        InventoryMovement::query()->create([
            'medicine_id' => $medicine->getKey(),
            'inventory_lot_id' => $lotId,
            'sale_item_id' => $saleItemId,
            'actor_user_id' => $actor->getKey(),
            'movement_type' => 'sale',
            'quantity_delta' => -2,
            'source_type' => 'sale',
            'source_id' => $saleId,
            'occurred_at' => $receivedAt,
        ]);

        return [$sale, $lotId];
    }

    private function markPaid(string $path): TestResponse
    {
        $key = (new EncryptedTransportRequestBuilder)->buildBodyless();
        $this->lastTransportKey = $key['aesKey'];

        return $this->call('POST', $path, [], [], [], [
            'HTTP_X_CSRF_TOKEN' => 'test-csrf-token',
            'HTTP_X_TRANSPORT_KEY' => $key['header'],
        ]);
    }

    private function responseBody(TestResponse $response, string $path): string
    {
        return (new EncryptedTransportResponseReader)
            ->read($response, $this->lastTransportKey, 'POST', $path)['body'];
    }
}
