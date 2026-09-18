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

final class MedicineDetailEndpointTest extends TestCase
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
        $this->actingAs($this->createUser(), 'web');
    }

    public function test_it_reads_archived_medicine_details_and_hides_expired_stock(): void
    {
        $actor = User::query()->firstOrFail();
        $medicine = Medicine::factory()->create([
            'generic_name' => 'Archived example medicine',
            'active' => false,
            'unit_price' => '24.90',
        ]);
        $this->addLot($medicine, $actor, 9, '2027-06-30');
        $this->addLot($medicine, $actor, 25, '2026-09-17');

        $path = '/api/v1/medicines/'.$medicine->getKey();
        $payload = $this->getPayload($path);

        $this->assertSame([
            'id' => $medicine->getKey(),
            'genericName' => 'Archived example medicine',
            'brandName' => 'Example brand',
            'description' => 'An example medicine for tests.',
            'dosageForm' => 'tablet',
            'strength' => '500 mg',
            'unitPrice' => '24.90',
            'storageLocation' => 'A-03',
            'active' => false,
            'stockOnHand' => 9,
            'createdAt' => '2026-09-18T02:00:00Z',
            'updatedAt' => '2026-09-18T02:00:00Z',
        ], $payload['data']);

        $missingPath = '/api/v1/medicines/999999';
        $request = (new EncryptedTransportRequestBuilder)->buildBodyless();
        $response = $this->call('GET', $missingPath, [], [], [], [
            'HTTP_X_TRANSPORT_KEY' => $request['header'],
        ])->assertNotFound();
        $body = (new EncryptedTransportResponseReader)
            ->read($response, $request['aesKey'], 'GET', $missingPath)['body'];
        $error = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('not_found', $error['error']['code']);
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

    /** @return array{data: array<string, mixed>} */
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
