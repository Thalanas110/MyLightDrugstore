<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Catalog;

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

final class MedicineListEndpointTest extends TestCase
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
        config()->set('inventory.low_stock_threshold', 30);
        $this->travelTo(CarbonImmutable::parse('2026-09-18T02:00:00Z'));
        $this->actingAs($this->createUser(), 'web');
    }

    public function test_it_searches_active_medicines_and_filters_by_available_stock_and_expiry(): void
    {
        $actor = User::query()->firstOrFail();
        $capsules = Medicine::factory()->create([
            'generic_name' => 'Amoxicillin capsules',
            'unit_price' => '12.50',
            'active' => true,
        ]);
        $tablets = Medicine::factory()->create([
            'generic_name' => 'Amoxicillin tablets',
            'unit_price' => '18.25',
            'active' => true,
        ]);
        $highStock = Medicine::factory()->create(['generic_name' => 'Amoxicillin syrup', 'active' => true]);
        Medicine::factory()->create(['generic_name' => 'Amoxicillin archived', 'active' => false]);
        $this->addLot($capsules, $actor, 10, '2026-12-01');
        $this->addLot($capsules, $actor, 50, '2026-09-17');
        $this->addLot($tablets, $actor, 3, '2026-11-01');
        $this->addLot($highStock, $actor, 30, '2026-10-01');

        $firstPage = $this->getPayload('/api/v1/medicines?q=amox&active=true&lowStock=true&expiresBefore=2026-12-01&perPage=1');

        $this->assertSame([$capsules->getKey()], array_column($firstPage['data'], 'id'));
        $this->assertSame(['page' => 1, 'perPage' => 1, 'total' => 2], $firstPage['meta']);
        $this->assertSame([
            'id' => $capsules->getKey(),
            'genericName' => 'Amoxicillin capsules',
            'brandName' => 'Example brand',
            'description' => 'An example medicine for tests.',
            'dosageForm' => 'tablet',
            'strength' => '500 mg',
            'unitPrice' => '12.50',
            'storageLocation' => 'A-03',
            'active' => true,
            'stockOnHand' => 10,
            'createdAt' => '2026-09-18T02:00:00Z',
            'updatedAt' => '2026-09-18T02:00:00Z',
        ], $firstPage['data'][0]);

        $secondPage = $this->getPayload('/api/v1/medicines?q=amox&active=true&lowStock=true&expiresBefore=2026-12-01&perPage=1&page=2');
        $this->assertSame([$tablets->getKey()], array_column($secondPage['data'], 'id'));
        $archived = $this->getPayload('/api/v1/medicines?q=amox&active=false');
        $this->assertCount(1, $archived['data']);
        $this->assertSame('Amoxicillin archived', $archived['data'][0]['genericName']);
        $this->assertSame(0, $archived['data'][0]['stockOnHand']);

        $path = '/api/v1/medicines?active=maybe&lowStock=unknown&perPage=101';
        $request = (new EncryptedTransportRequestBuilder)->buildBodyless();
        $response = $this->call('GET', $path, [], [], [], [
            'HTTP_X_TRANSPORT_KEY' => $request['header'],
        ])->assertUnprocessable();
        $body = (new EncryptedTransportResponseReader)
            ->read($response, $request['aesKey'], 'GET', $path)['body'];
        $error = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('validation_failed', $error['error']['code']);
        $this->assertArrayHasKey('active', $error['error']['details']);
        $this->assertArrayHasKey('lowStock', $error['error']['details']);
        $this->assertArrayHasKey('perPage', $error['error']['details']);
    }

    private function createUser(): User
    {
        $user = new User;
        $user->setUsername(new Username('catalog.staff'));
        $user->setFullName(new FullName('Catalog Staff'));
        $user->password = 'CorrectHorseBatteryStaple!2026';
        $user->role = StaffRole::Staff;
        $user->active = true;
        $user->save();

        return $user;
    }

    private function addLot(Medicine $medicine, User $actor, int $quantity, string $expiresAt): void
    {
        $receipt = InventoryReceipt::query()->create([
            'actor_user_id' => $actor->getKey(),
            'received_at' => '2026-09-18T02:00:00Z',
        ]);
        InventoryLot::query()->create([
            'receipt_id' => $receipt->getKey(),
            'medicine_id' => $medicine->getKey(),
            'received_at' => '2026-09-18T02:00:00Z',
            'expires_at' => $expiresAt,
            'quantity_received' => $quantity,
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
