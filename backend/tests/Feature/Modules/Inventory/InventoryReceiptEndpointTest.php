<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Catalog\Infrastructure\Persistence\Medicine;
use App\Modules\Identity\Domain\Users\FullName;
use App\Modules\Identity\Domain\Users\StaffRole;
use App\Modules\Identity\Domain\Users\Username;
use App\Modules\Identity\Infrastructure\Persistence\User;
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

final class InventoryReceiptEndpointTest extends TestCase
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
    }

    public function test_it_receives_stock_into_expiring_lots_and_records_actor_movements(): void
    {
        $actor = $this->createUser();
        $this->authenticate($actor);
        $firstMedicine = Medicine::factory()->create();
        $secondMedicine = Medicine::factory()->create();

        $response = $this->encryptedRequest('POST', '/api/v1/inventory/receipts', [
            'receivedAt' => '2026-09-18T02:00:00Z',
            'items' => [
                ['medicineId' => $firstMedicine->getKey(), 'quantity' => 24, 'expiresAt' => '2028-06-30'],
                ['medicineId' => $secondMedicine->getKey(), 'quantity' => 12, 'expiresAt' => '2029-03-31'],
            ],
        ], $this->requestCsrfToken())->assertCreated();

        $body = $this->responseBody($response, 'POST', '/api/v1/inventory/receipts');
        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(2, $payload['data']['itemCount']);
        $this->assertSame(36, $payload['data']['totalQuantity']);
        $this->assertSame('2026-09-18T02:00:00Z', $payload['data']['receivedAt']);
        $this->assertDatabaseCount('inventory_receipts', 1);
        $this->assertDatabaseCount('inventory_lots', 2);
        $this->assertDatabaseCount('inventory_movements', 2);

        $receipt = InventoryReceipt::query()->with('lots')->firstOrFail();
        $this->assertSame($actor->getKey(), $receipt->actor_user_id);
        $this->assertCount(2, $receipt->lots);
        $this->assertSame([24, 12], $receipt->lots->pluck('quantity_remaining')->sortDesc()->values()->all());
        $this->assertSame([$actor->getKey()], InventoryMovement::query()->distinct()->pluck('actor_user_id')->all());
        $this->assertSame(
            [$receipt->getKey()],
            InventoryMovement::query()->distinct()->where('source_type', 'inventory_receipt')->pluck('source_id')->all(),
        );
    }

    public function test_it_rejects_unknown_medicines_without_recording_a_partial_receipt(): void
    {
        $this->authenticate($this->createUser());
        $medicine = Medicine::factory()->create();

        $response = $this->encryptedRequest('POST', '/api/v1/inventory/receipts', [
            'receivedAt' => '2026-09-18T02:00:00Z',
            'items' => [
                ['medicineId' => $medicine->getKey(), 'quantity' => 24, 'expiresAt' => '2028-06-30'],
                ['medicineId' => 999999, 'quantity' => 12, 'expiresAt' => '2029-03-31'],
            ],
        ], $this->requestCsrfToken())->assertUnprocessable();

        $body = $this->responseBody($response, 'POST', '/api/v1/inventory/receipts');
        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('validation_failed', $payload['error']['code']);
        $this->assertArrayHasKey('items.1.medicineId', $payload['error']['details']);
        $this->assertDatabaseCount('inventory_receipts', 0);
        $this->assertDatabaseCount('inventory_lots', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_it_rejects_stock_that_expires_before_its_receipt_date(): void
    {
        $this->authenticate($this->createUser());
        $medicine = Medicine::factory()->create();

        $response = $this->encryptedRequest('POST', '/api/v1/inventory/receipts', [
            'receivedAt' => '2026-09-18T02:00:00Z',
            'items' => [
                ['medicineId' => $medicine->getKey(), 'quantity' => 24, 'expiresAt' => '2026-09-17'],
            ],
        ], $this->requestCsrfToken())->assertUnprocessable();
        $body = $this->responseBody($response, 'POST', '/api/v1/inventory/receipts');
        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('validation_failed', $payload['error']['code']);
        $this->assertArrayHasKey('items.0.expiresAt', $payload['error']['details']);
        $this->assertDatabaseCount('inventory_receipts', 0);
        $this->assertDatabaseCount('inventory_lots', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_a_persistence_failure_rolls_back_the_entire_multi_item_receipt(): void
    {
        $this->authenticate($this->createUser());
        $firstMedicine = Medicine::factory()->create();
        $secondMedicine = Medicine::factory()->create();
        $movementInserts = 0;

        DB::listen(static function (QueryExecuted $query) use (&$movementInserts): void {
            if (str_starts_with(strtolower($query->sql), 'insert') && str_contains($query->sql, 'inventory_movements')) {
                $movementInserts++;

                if ($movementInserts === 2) {
                    throw new RuntimeException('Simulated movement persistence failure.');
                }
            }
        });

        $response = $this->encryptedRequest('POST', '/api/v1/inventory/receipts', [
            'receivedAt' => '2026-09-18T02:00:00Z',
            'items' => [
                ['medicineId' => $firstMedicine->getKey(), 'quantity' => 24, 'expiresAt' => '2028-06-30'],
                ['medicineId' => $secondMedicine->getKey(), 'quantity' => 12, 'expiresAt' => '2029-03-31'],
            ],
        ], $this->requestCsrfToken())->assertInternalServerError();
        $body = $this->responseBody($response, 'POST', '/api/v1/inventory/receipts');
        $this->assertSame('internal_error', json_decode($body, true, 512, JSON_THROW_ON_ERROR)['error']['code']);
        $this->assertDatabaseCount('inventory_receipts', 0);
        $this->assertDatabaseCount('inventory_lots', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
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

    private function authenticate(User $user): void
    {
        $csrfToken = $this->requestCsrfToken();
        $this->encryptedRequest('POST', '/api/v1/auth/login', [
            'username' => $user->username()->value,
            'password' => 'CorrectHorseBatteryStaple!2026',
        ], $csrfToken)->assertOk();
    }

    private function requestCsrfToken(): string
    {
        $this->withSession(['_token' => 'test-csrf-token']);
        $response = $this->encryptedRequest('GET', '/api/v1/auth/csrf')->assertOk();
        $body = $this->responseBody($response, 'GET', '/api/v1/auth/csrf');
        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $token = $payload['data']['csrfToken'] ?? null;

        $this->assertIsString($token);

        return $token;
    }

    /** @param array<string, mixed> $payload */
    private function encryptedRequest(string $method, string $path, array $payload = [], ?string $csrfToken = null): TestResponse
    {
        $builder = new EncryptedTransportRequestBuilder;
        $headers = [];

        if ($payload === []) {
            $key = $builder->buildBodyless();
            $body = '';
        } else {
            $key = $builder->build($method, $path, $payload);
            $body = json_encode($key['envelope'], JSON_THROW_ON_ERROR);
        }

        if ($csrfToken !== null) {
            $headers['HTTP_X_CSRF_TOKEN'] = $csrfToken;
        }

        $headers['HTTP_X_TRANSPORT_KEY'] = $key['header'];
        $this->lastTransportKey = $key['aesKey'];

        return $this->call($method, $path, [], [], [], $headers + ['CONTENT_TYPE' => 'application/json'], $body);
    }

    private function responseBody(TestResponse $response, string $method, string $path): string
    {
        return (new EncryptedTransportResponseReader)
            ->read($response, $this->lastTransportKey, $method, $path)['body'];
    }
}
