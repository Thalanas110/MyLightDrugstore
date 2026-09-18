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

final class InventoryLotListEndpointTest extends TestCase
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
        $this->travelTo(CarbonImmutable::parse('2026-09-18T02:00:00Z'));
    }

    public function test_it_lists_lots_by_expiry_with_stock_and_availability_details(): void
    {
        $this->actingAs($this->createUser(), 'web');
        $medicine = Medicine::factory()->create(['generic_name' => 'Amoxicillin']);
        $later = $this->addLot($medicine, 10, '2027-06-30', '2026-09-18T04:00:00Z');
        $earlier = $this->addLot($medicine, 8, '2027-01-31', '2026-09-18T03:00:00Z');
        $depleted = $this->addLot($medicine, 0, '2026-10-01', '2026-09-18T02:00:00Z', receivedQuantity: 6);
        $expired = $this->addLot($medicine, 3, '2026-09-17', '2026-09-18T01:00:00Z');

        $payload = $this->getPayload('/api/v1/inventory/lots');

        $this->assertSame([
            $expired->getKey(),
            $depleted->getKey(),
            $earlier->getKey(),
            $later->getKey(),
        ], array_column($payload['data'], 'lotId'));
        $this->assertSame([
            'lotId' => $expired->getKey(),
            'medicineId' => $medicine->getKey(),
            'genericName' => 'Amoxicillin',
            'brandName' => 'Example brand',
            'receivedAt' => '2026-09-18T01:00:00+00:00',
            'expiresAt' => '2026-09-17',
            'quantityReceived' => 3,
            'quantityRemaining' => 3,
            'available' => false,
        ], $payload['data'][0]);
        $this->assertSame(['page' => 1, 'perPage' => 25, 'total' => 4], $payload['meta']);
        $this->assertFalse($payload['data'][1]['available']);
        $this->assertTrue($payload['data'][2]['available']);
    }

    public function test_it_filters_lots_by_medicine_expiry_and_availability_then_paginates(): void
    {
        $this->actingAs($this->createUser(), 'web');
        $firstMedicine = Medicine::factory()->create(['generic_name' => 'Amoxicillin']);
        $secondMedicine = Medicine::factory()->create(['generic_name' => 'Ibuprofen']);
        $first = $this->addLot($firstMedicine, 10, '2027-01-01');
        $this->addLot($firstMedicine, 0, '2026-12-01', receivedQuantity: 3);
        $this->addLot($firstMedicine, 4, '2027-03-01');
        $this->addLot($secondMedicine, 7, '2026-11-01');

        $payload = $this->getPayload('/api/v1/inventory/lots?medicineId='.$firstMedicine->getKey().'&expiresBefore=2027-01-01&available=true&perPage=1&page=1');

        $this->assertSame([$first->getKey()], array_column($payload['data'], 'lotId'));
        $this->assertSame(['page' => 1, 'perPage' => 1, 'total' => 1], $payload['meta']);

        $unavailable = $this->getPayload('/api/v1/inventory/lots?medicineId='.$firstMedicine->getKey().'&available=false');
        $this->assertCount(1, $unavailable['data']);
        $this->assertFalse($unavailable['data'][0]['available']);
    }

    public function test_it_rejects_invalid_lot_filters_with_an_encrypted_validation_error(): void
    {
        $this->actingAs($this->createUser(), 'web');
        $path = '/api/v1/inventory/lots?medicineId=not-a-number&expiresBefore=tomorrow&available=sometimes';
        $request = (new EncryptedTransportRequestBuilder)->buildBodyless();
        $response = $this->call('GET', $path, [], [], [], [
            'HTTP_X_TRANSPORT_KEY' => $request['header'],
        ])->assertUnprocessable();
        $body = (new EncryptedTransportResponseReader)
            ->read($response, $request['aesKey'], 'GET', $path)['body'];
        $error = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('validation_failed', $error['error']['code']);
        $this->assertArrayHasKey('medicineId', $error['error']['details']);
        $this->assertArrayHasKey('expiresBefore', $error['error']['details']);
        $this->assertArrayHasKey('available', $error['error']['details']);
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

    private function addLot(
        Medicine $medicine,
        int $quantity,
        string $expiresAt,
        string $receivedAt = '2026-09-18T02:00:00Z',
        ?int $receivedQuantity = null,
    ): InventoryLot {
        $receipt = InventoryReceipt::query()->create([
            'actor_user_id' => User::query()->firstOrFail()->getKey(),
            'received_at' => $receivedAt,
        ]);

        return InventoryLot::query()->create([
            'receipt_id' => $receipt->getKey(),
            'medicine_id' => $medicine->getKey(),
            'received_at' => $receivedAt,
            'expires_at' => $expiresAt,
            'quantity_received' => $receivedQuantity ?? $quantity,
            'quantity_remaining' => $quantity,
        ]);
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, int>} */
    private function getPayload(string $path): array
    {
        $request = (new EncryptedTransportRequestBuilder)->buildBodyless();
        $response = $this->call('GET', $path, [], [], [], [
            'HTTP_X_TRANSPORT_KEY' => $request['header'],
        ])->assertOk();
        $body = (new EncryptedTransportResponseReader)
            ->read($response, $request['aesKey'], 'GET', $path)['body'];

        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }
}
