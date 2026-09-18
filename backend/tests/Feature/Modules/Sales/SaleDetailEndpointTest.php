<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Sales;

use App\Modules\Catalog\Infrastructure\Persistence\Medicine;
use App\Modules\Identity\Domain\Users\FullName;
use App\Modules\Identity\Domain\Users\StaffRole;
use App\Modules\Identity\Domain\Users\Username;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Sales\Infrastructure\Persistence\Sale;
use App\Modules\Sales\Infrastructure\Persistence\SaleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use LogicException;
use Tests\Support\EncryptedTransportRequestBuilder;
use Tests\Support\EncryptedTransportResponseReader;
use Tests\TestCase;

final class SaleDetailEndpointTest extends TestCase
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
    }

    public function test_it_returns_saved_sale_details_and_price_snapshots(): void
    {
        $actor = User::query()->firstOrFail();
        $medicine = Medicine::factory()->create();
        $sale = Sale::factory()->create([
            'created_by_user_id' => $actor->getKey(),
            'state' => 'completed',
            'payment_status' => 'paid',
            'total' => '24.75',
        ]);
        $saleItem = SaleItem::factory()->create([
            'sale_id' => $sale->getKey(),
            'medicine_id' => $medicine->getKey(),
            'quantity' => 3,
            'unit_price' => '8.25',
            'line_total' => '24.75',
            'state' => 'active',
        ]);
        $saleId = $sale->getKey();
        $saleItemId = $saleItem->getKey();

        if (! is_int($saleId) || ! is_int($saleItemId)) {
            throw new LogicException('The sale test graph was created without valid identifiers.');
        }

        $path = '/api/v1/sales/'.$saleId;
        $response = $this->encryptedGet($path)->assertOk();
        $payload = json_decode($this->responseBody($response, $path), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($saleId, $payload['data']['id']);
        $this->assertSame($actor->getKey(), $payload['data']['createdBy']);
        $this->assertSame('completed', $payload['data']['state']);
        $this->assertSame('paid', $payload['data']['paymentStatus']);
        $this->assertSame('24.75', $payload['data']['total']);
        $this->assertSame($saleItemId, $payload['data']['items'][0]['id']);
        $this->assertSame($medicine->getKey(), $payload['data']['items'][0]['medicineId']);
        $this->assertSame(3, $payload['data']['items'][0]['quantity']);
        $this->assertSame('8.25', $payload['data']['items'][0]['unitPrice']);
        $this->assertSame('24.75', $payload['data']['items'][0]['lineTotal']);
    }

    public function test_it_returns_an_encrypted_not_found_error_for_a_missing_sale(): void
    {
        $path = '/api/v1/sales/999999';
        $response = $this->encryptedGet($path)->assertNotFound();
        $payload = json_decode($this->responseBody($response, $path), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('not_found', $payload['error']['code']);
    }

    private function createUser(): User
    {
        $user = new User;
        $user->setUsername(new Username('sales.detail.staff'));
        $user->setFullName(new FullName('Sales Detail Staff'));
        $user->password = 'CorrectHorseBatteryStaple!2026';
        $user->role = StaffRole::Staff;
        $user->active = true;
        $user->save();

        return $user;
    }

    private function encryptedGet(string $path): TestResponse
    {
        $key = (new EncryptedTransportRequestBuilder)->buildBodyless();
        $this->lastTransportKey = $key['aesKey'];

        return $this->call('GET', $path, [], [], [], [
            'HTTP_X_TRANSPORT_KEY' => $key['header'],
        ]);
    }

    private function responseBody(TestResponse $response, string $path): string
    {
        return (new EncryptedTransportResponseReader)
            ->read($response, $this->lastTransportKey, 'GET', $path)['body'];
    }
}
