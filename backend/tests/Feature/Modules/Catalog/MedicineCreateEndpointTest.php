<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Catalog;

use App\Modules\Identity\Domain\Users\FullName;
use App\Modules\Identity\Domain\Users\StaffRole;
use App\Modules\Identity\Domain\Users\Username;
use App\Modules\Identity\Infrastructure\Persistence\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\EncryptedTransportRequestBuilder;
use Tests\Support\EncryptedTransportResponseReader;
use Tests\TestCase;

final class MedicineCreateEndpointTest extends TestCase
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

    public function test_it_creates_a_medicine_and_rejects_out_of_range_price_and_unknown_fields(): void
    {
        $response = $this->createMedicine([
            'genericName' => 'Amoxicillin',
            'brandName' => 'Moxy',
            'description' => 'An antibiotic capsule.',
            'dosageForm' => 'capsule',
            'strength' => '250 mg',
            'unitPrice' => '12.50',
            'storageLocation' => 'B-12',
        ])->assertCreated();
        $payload = json_decode($this->responseBody($response), true, 512, JSON_THROW_ON_ERROR);
        $medicine = $payload['data'];

        $this->assertSame('Amoxicillin', $medicine['genericName']);
        $this->assertSame('Moxy', $medicine['brandName']);
        $this->assertSame('capsule', $medicine['dosageForm']);
        $this->assertSame('250 mg', $medicine['strength']);
        $this->assertSame('12.50', $medicine['unitPrice']);
        $this->assertSame('B-12', $medicine['storageLocation']);
        $this->assertSame(0, $medicine['stockOnHand']);
        $this->assertTrue($medicine['active']);
        $this->assertDatabaseCount('medicines', 1);
        $this->assertDatabaseHas('medicines', [
            'id' => $medicine['id'],
            'generic_name' => 'Amoxicillin',
            'unit_price' => '12.50',
            'active' => true,
        ]);

        $invalidResponse = $this->createMedicine([
            'genericName' => 'Invalid medicine',
            'brandName' => null,
            'description' => null,
            'dosageForm' => 'tablet',
            'strength' => null,
            'unitPrice' => '100000000.00',
            'storageLocation' => null,
            'initialQuantity' => 80,
        ])->assertUnprocessable();
        $error = json_decode($this->responseBody($invalidResponse), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('validation_failed', $error['error']['code']);
        $this->assertArrayHasKey('unitPrice', $error['error']['details']);
        $this->assertArrayHasKey('initialQuantity', $error['error']['details']);
        $this->assertDatabaseCount('medicines', 1);
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
    private function createMedicine(array $payload): TestResponse
    {
        $path = '/api/v1/medicines';
        $key = (new EncryptedTransportRequestBuilder)->build('POST', $path, $payload);
        $this->lastTransportKey = $key['aesKey'];

        return $this->call('POST', $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => 'test-csrf-token',
            'HTTP_X_TRANSPORT_KEY' => $key['header'],
        ], json_encode($key['envelope'], JSON_THROW_ON_ERROR));
    }

    private function responseBody(TestResponse $response): string
    {
        return (new EncryptedTransportResponseReader)
            ->read($response, $this->lastTransportKey, 'POST', '/api/v1/medicines')['body'];
    }
}
