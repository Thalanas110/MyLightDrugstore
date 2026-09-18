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
use Illuminate\Testing\TestResponse;
use Tests\Support\EncryptedTransportRequestBuilder;
use Tests\Support\EncryptedTransportResponseReader;
use Tests\TestCase;

final class MedicineArchiveEndpointTest extends TestCase
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
        $this->travelTo(CarbonImmutable::parse('2026-09-18T02:00:00Z'));
        $this->actingAs($this->createUser(), 'web');
        $this->withSession(['_token' => 'test-csrf-token']);
    }

    public function test_it_archives_a_medicine_once_without_removing_its_stock_history(): void
    {
        $actor = User::query()->firstOrFail();
        $medicine = Medicine::factory()->create(['generic_name' => 'Archived medicine']);
        $receipt = InventoryReceipt::query()->create([
            'actor_user_id' => $actor->getKey(),
            'received_at' => '2026-09-18T02:00:00Z',
        ]);
        $lot = InventoryLot::query()->create([
            'receipt_id' => $receipt->getKey(),
            'medicine_id' => $medicine->getKey(),
            'received_at' => '2026-09-18T02:00:00Z',
            'expires_at' => '2027-06-30',
            'quantity_received' => 12,
            'quantity_remaining' => 12,
        ]);
        $path = '/api/v1/medicines/'.$medicine->getKey().'/archive';

        $first = $this->archive($path);
        $first->assertOk();
        $firstBody = $this->responseBody($first, $path);
        $firstMedicine = json_decode($firstBody, true, 512, JSON_THROW_ON_ERROR)['data'];
        $this->assertFalse($firstMedicine['active']);
        $this->assertSame(12, $firstMedicine['stockOnHand']);
        $this->assertDatabaseHas('medicines', ['id' => $medicine->getKey(), 'active' => false]);
        $this->assertDatabaseHas('inventory_lots', ['id' => $lot->getKey(), 'quantity_remaining' => 12]);

        $second = $this->archive($path);
        $second->assertOk();
        $secondMedicine = json_decode($this->responseBody($second, $path), true, 512, JSON_THROW_ON_ERROR)['data'];
        $this->assertSame($firstMedicine['updatedAt'], $secondMedicine['updatedAt']);
        $this->assertFalse($secondMedicine['active']);
        $this->assertDatabaseCount('inventory_lots', 1);

        $missingPath = '/api/v1/medicines/999999/archive';
        $missing = $this->archive($missingPath)->assertNotFound();
        $missingError = json_decode($this->responseBody($missing, $missingPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('not_found', $missingError['error']['code']);
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

    private function archive(string $path): TestResponse
    {
        $key = (new EncryptedTransportRequestBuilder)->buildBodyless();
        $response = $this->call('POST', $path, [], [], [], [
            'HTTP_X_CSRF_TOKEN' => 'test-csrf-token',
            'HTTP_X_TRANSPORT_KEY' => $key['header'],
        ]);

        $this->lastTransportKey = $key['aesKey'];

        return $response;
    }

    private function responseBody(TestResponse $response, string $path): string
    {
        return (new EncryptedTransportResponseReader)
            ->read($response, $this->lastTransportKey, 'POST', $path)['body'];
    }
}
