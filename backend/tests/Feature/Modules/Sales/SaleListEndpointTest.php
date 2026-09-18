<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Sales;

use App\Modules\Identity\Domain\Users\FullName;
use App\Modules\Identity\Domain\Users\StaffRole;
use App\Modules\Identity\Domain\Users\Username;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Sales\Infrastructure\Persistence\Sale;
use App\Modules\Sales\Infrastructure\Persistence\SaleItem;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Support\EncryptedTransportRequestBuilder;
use Tests\Support\EncryptedTransportResponseReader;
use Tests\TestCase;

final class SaleListEndpointTest extends TestCase
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
        $this->actingAs($this->createUser(), 'web');
    }

    public function test_it_filters_and_pages_sales_newest_first(): void
    {
        $actor = User::query()->firstOrFail();
        $olderSale = $this->createSale($actor, 'completed', 'paid', '20.00', '2026-09-18T10:00:00Z', 1);
        $newerSale = $this->createSale($actor, 'completed', 'paid', '30.00', '2026-09-18T11:00:00Z', 2);
        SaleItem::factory()->create(['sale_id' => $newerSale->getKey(), 'state' => 'removed']);
        $otherActor = $this->createUser('sales.list.other');
        $this->createSale($otherActor, 'completed', 'paid', '100.00', '2026-09-18T13:00:00Z', 1);
        $this->createSale($actor, 'cancelled', 'unpaid', '4.00', '2026-09-18T12:00:00Z', 3);

        $path = '/api/v1/sales?paymentStatus=paid&state=completed&from=2026-09-18&to=2026-09-18&createdBy='
            .$actor->getKey().'&page=1&perPage=1';
        $payload = $this->getPayload($path);
        $newerSaleId = $newerSale->getKey();

        if (! is_int($newerSaleId)) {
            throw new LogicException('The sale list fixture was created without a valid identifier.');
        }

        $this->assertSame([$newerSaleId], array_column($payload['data'], 'id'));
        $this->assertSame('30.00', $payload['data'][0]['total']);
        $this->assertSame('completed', $payload['data'][0]['state']);
        $this->assertSame('paid', $payload['data'][0]['paymentStatus']);
        $this->assertSame(2, $payload['data'][0]['itemCount']);
        $this->assertSame([
            'page' => 1,
            'perPage' => 1,
            'total' => 2,
        ], $payload['meta']);
        $this->assertNotSame($olderSale->getKey(), $newerSaleId);
    }

    public function test_it_accepts_an_end_date_without_a_start_date(): void
    {
        $actor = User::query()->firstOrFail();
        $this->createSale($actor, 'open', 'unpaid', '3.00', '2026-09-18T12:00:00Z', 1);

        $payload = $this->getPayload('/api/v1/sales?to=2026-09-18');

        $this->assertSame(1, $payload['meta']['total']);
    }

    public function test_it_rejects_invalid_or_unknown_sale_filters(): void
    {
        $path = '/api/v1/sales?paymentStatus=refunded&state=void&from=2026-09-20&to=2026-09-18&perPage=101&unknown=1';
        $request = (new EncryptedTransportRequestBuilder)->buildBodyless();
        $response = $this->call('GET', $path, [], [], [], [
            'HTTP_X_TRANSPORT_KEY' => $request['header'],
        ])->assertUnprocessable();
        $body = (new EncryptedTransportResponseReader)
            ->read($response, $request['aesKey'], 'GET', $path)['body'];
        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('validation_failed', $payload['error']['code']);
        $this->assertArrayHasKey('paymentStatus', $payload['error']['details']);
        $this->assertArrayHasKey('state', $payload['error']['details']);
        $this->assertArrayHasKey('to', $payload['error']['details']);
        $this->assertArrayHasKey('perPage', $payload['error']['details']);
        $this->assertArrayHasKey('unknown', $payload['error']['details']);
    }

    private function createUser(string $username = 'sales.list.staff'): User
    {
        $user = new User;
        $user->setUsername(new Username($username));
        $user->setFullName(new FullName('Sales List Staff'));
        $user->password = 'CorrectHorseBatteryStaple!2026';
        $user->role = StaffRole::Staff;
        $user->active = true;
        $user->save();

        return $user;
    }

    private function createSale(
        User $actor,
        string $state,
        string $paymentStatus,
        string $total,
        string $createdAt,
        int $itemCount,
    ): Sale {
        $sale = Sale::factory()->create([
            'created_by_user_id' => $actor->getKey(),
            'state' => $state,
            'payment_status' => $paymentStatus,
            'total' => $total,
            'created_at' => CarbonImmutable::parse($createdAt),
            'updated_at' => CarbonImmutable::parse($createdAt),
        ]);

        SaleItem::factory()->count($itemCount)->create(['sale_id' => $sale->getKey()]);

        return $sale;
    }

    /** @return array{data: list<array<string, mixed>>, meta: array{page: int, perPage: int, total: int}} */
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
