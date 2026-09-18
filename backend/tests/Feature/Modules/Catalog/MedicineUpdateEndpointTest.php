<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Catalog;

use App\Modules\Catalog\Infrastructure\Persistence\Medicine;
use App\Modules\Identity\Domain\Users\FullName;
use App\Modules\Identity\Domain\Users\StaffRole;
use App\Modules\Identity\Domain\Users\Username;
use App\Modules\Identity\Infrastructure\Persistence\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\EncryptedTransportRequestBuilder;
use Tests\Support\EncryptedTransportResponseReader;
use Tests\TestCase;

final class MedicineUpdateEndpointTest extends TestCase
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

    public function test_it_updates_only_submitted_catalog_fields_and_rejects_price_or_state_changes(): void
    {
        $medicine = Medicine::factory()->create();
        $path = '/api/v1/medicines/'.$medicine->getKey();

        $response = $this->updateMedicine($path, [
            'genericName' => 'Updated medicine name',
            'brandName' => null,
            'unitPrice' => '18.75',
            'strength' => null,
        ])->assertOk();
        $payload = json_decode($this->responseBody($response, $path), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('Updated medicine name', $payload['data']['genericName']);
        $this->assertNull($payload['data']['brandName']);
        $this->assertSame('18.75', $payload['data']['unitPrice']);
        $this->assertNull($payload['data']['strength']);
        $this->assertSame('tablet', $payload['data']['dosageForm']);
        $this->assertTrue($payload['data']['active']);
        $this->assertSame(0, $payload['data']['stockOnHand']);

        $invalidResponse = $this->updateMedicine($path, [
            'unitPrice' => '100000000.00',
            'active' => false,
        ])->assertUnprocessable();
        $error = json_decode($this->responseBody($invalidResponse, $path), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('validation_failed', $error['error']['code']);
        $this->assertArrayHasKey('unitPrice', $error['error']['details']);
        $this->assertArrayHasKey('active', $error['error']['details']);
        $this->assertDatabaseHas('medicines', [
            'id' => $medicine->getKey(),
            'generic_name' => 'Updated medicine name',
            'unit_price' => '18.75',
            'active' => true,
        ]);

        $missingPath = '/api/v1/medicines/999999';
        $missingResponse = $this->updateMedicine($missingPath, ['genericName' => 'Missing'])->assertNotFound();
        $missingError = json_decode($this->responseBody($missingResponse, $missingPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('not_found', $missingError['error']['code']);

        $emptyResponse = $this->updateMedicine($path, [])->assertUnprocessable();
        $emptyError = json_decode($this->responseBody($emptyResponse, $path), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('validation_failed', $emptyError['error']['code']);
        $this->assertArrayHasKey('medicine', $emptyError['error']['details']);
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

    /** @param array<string, mixed> $payload */
    private function updateMedicine(string $path, array $payload): TestResponse
    {
        $key = (new EncryptedTransportRequestBuilder)->build('PATCH', $path, $payload);
        $this->lastTransportKey = $key['aesKey'];

        return $this->call('PATCH', $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => 'test-csrf-token',
            'HTTP_X_TRANSPORT_KEY' => $key['header'],
        ], json_encode($key['envelope'], JSON_THROW_ON_ERROR));
    }

    private function responseBody(TestResponse $response, string $path): string
    {
        return (new EncryptedTransportResponseReader)
            ->read($response, $this->lastTransportKey, 'PATCH', $path)['body'];
    }
}
