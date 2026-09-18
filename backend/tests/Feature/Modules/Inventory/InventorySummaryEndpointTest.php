<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Catalog\Infrastructure\Persistence\Medicine;
use App\Modules\Identity\Domain\Users\FullName;
use App\Modules\Identity\Domain\Users\StaffRole;
use App\Modules\Identity\Domain\Users\Username;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Inventory\Infrastructure\Persistence\InventoryLot;
use App\Modules\Inventory\Infrastructure\Persistence\InventoryReceipt;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EncryptedTransportRequestBuilder;
use Tests\Support\EncryptedTransportResponseReader;
use Tests\TestCase;

final class InventorySummaryEndpointTest extends TestCase
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

    public function test_it_summarizes_lots_filters_low_stock_and_returns_pagination_metadata(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-18T02:00:00Z'));
        $this->actingAs($this->createUser(), 'web');
        $lowStockMedicine = Medicine::factory()->create(['generic_name' => 'Low stock medicine']);
        $thresholdMedicine = Medicine::factory()->create(['generic_name' => 'At threshold medicine']);
        $inactiveMedicine = Medicine::factory()->create(['active' => false, 'generic_name' => 'Archived medicine']);
        $this->addLot($lowStockMedicine, 10, '2027-03-31');
        $this->addLot($lowStockMedicine, 19, '2027-01-10');
        $this->addLot($lowStockMedicine, 100, '2026-09-17');
        $this->addLot($thresholdMedicine, 30, '2028-06-30');
        $this->addLot($inactiveMedicine, 1, '2028-06-30');

        $body = $this->requestBody('/api/v1/inventory?lowStock=true');
        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([
            'data' => [[
                'medicineId' => $lowStockMedicine->getKey(),
                'genericName' => 'Low stock medicine',
                'brandName' => 'Example brand',
                'dosageForm' => 'tablet',
                'strength' => '500 mg',
                'unitPrice' => '12.50',
                'stockOnHand' => 29,
                'lowStock' => true,
                'earliestExpiry' => '2027-01-10',
            ]],
            'meta' => ['page' => 1, 'perPage' => 25, 'total' => 1],
        ], $payload);

        $thresholdBody = $this->requestBody('/api/v1/inventory?lowStock=false');
        $thresholdPayload = json_decode($thresholdBody, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['At threshold medicine'], array_column($thresholdPayload['data'], 'genericName'));
        $this->assertFalse($thresholdPayload['data'][0]['lowStock']);
    }

    public function test_expiry_filter_ignores_depleted_lots(): void
    {
        $this->actingAs($this->createUser(), 'web');
        $medicineWithStock = Medicine::factory()->create(['generic_name' => 'Expiring medicine']);
        $medicineWithOnlyDepletedLots = Medicine::factory()->create(['generic_name' => 'Depleted medicine']);
        $this->addLot($medicineWithStock, 4, '2026-10-01');
        $this->addLot($medicineWithOnlyDepletedLots, 0, '2026-09-20', receivedQuantity: 12);
        $this->addLot($medicineWithOnlyDepletedLots, 3, '2026-09-17');

        $body = $this->requestBody('/api/v1/inventory?expiresBefore=2026-10-01');
        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertCount(1, $payload['data']);
        $this->assertSame('Expiring medicine', $payload['data'][0]['genericName']);
        $this->assertSame('2026-10-01', $payload['data'][0]['earliestExpiry']);
        $this->assertSame(1, $payload['meta']['total']);
    }

    public function test_a_medicine_without_receipts_has_zero_stock_and_no_expiry(): void
    {
        $this->actingAs($this->createUser(), 'web');
        Medicine::factory()->create(['generic_name' => 'Unstocked medicine']);

        $body = $this->requestBody('/api/v1/inventory?lowStock=true');
        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $payload['data'][0]['stockOnHand']);
        $this->assertTrue($payload['data'][0]['lowStock']);
        $this->assertNull($payload['data'][0]['earliestExpiry']);
    }

    public function test_inventory_summary_requires_authentication(): void
    {
        $path = '/api/v1/inventory';
        $request = (new EncryptedTransportRequestBuilder)->buildBodyless();
        $response = $this->call('GET', $path, [], [], [], [
            'HTTP_X_TRANSPORT_KEY' => $request['header'],
        ])->assertUnauthorized();
        $body = (new EncryptedTransportResponseReader)
            ->read($response, $request['aesKey'], 'GET', $path)['body'];

        $this->assertSame('unauthenticated', json_decode($body, true, 512, JSON_THROW_ON_ERROR)['error']['code']);
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

    private function addLot(Medicine $medicine, int $quantity, string $expiresAt, int $receivedQuantity = 0): void
    {
        $user = User::query()->firstOrFail();
        $receipt = InventoryReceipt::query()->create([
            'actor_user_id' => $user->getKey(),
            'received_at' => '2026-09-18T02:00:00Z',
        ]);

        InventoryLot::query()->create([
            'receipt_id' => $receipt->getKey(),
            'medicine_id' => $medicine->getKey(),
            'received_at' => '2026-09-18T02:00:00Z',
            'expires_at' => $expiresAt,
            'quantity_received' => $receivedQuantity ?: $quantity,
            'quantity_remaining' => $quantity,
        ]);
    }

    private function requestBody(string $path): string
    {
        $request = (new EncryptedTransportRequestBuilder)->buildBodyless();
        $response = $this->call('GET', $path, [], [], [], [
            'HTTP_X_TRANSPORT_KEY' => $request['header'],
        ])->assertOk();

        return (new EncryptedTransportResponseReader)
            ->read($response, $request['aesKey'], 'GET', $path)['body'];
    }
}
