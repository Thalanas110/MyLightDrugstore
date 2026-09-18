<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Catalog\Infrastructure\Persistence\Medicine;
use App\Modules\Identity\Domain\Users\FullName;
use App\Modules\Identity\Domain\Users\StaffRole;
use App\Modules\Identity\Domain\Users\Username;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Inventory\Infrastructure\Persistence\InventoryLot;
use App\Modules\Inventory\Infrastructure\Persistence\InventoryMovement;
use App\Modules\Inventory\Infrastructure\Persistence\InventoryReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EncryptedTransportRequestBuilder;
use Tests\Support\EncryptedTransportResponseReader;
use Tests\TestCase;

final class InventoryMovementListEndpointTest extends TestCase
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
        $user = $this->createUser();
        $this->actingAs($user, 'web');
    }

    public function test_it_filters_and_pages_the_movement_ledger_with_actor_and_lot_details(): void
    {
        $actor = User::query()->firstOrFail();
        $medicine = Medicine::factory()->create(['generic_name' => 'Amoxicillin']);
        $otherMedicine = Medicine::factory()->create(['generic_name' => 'Ibuprofen']);
        $lot = $this->createLot($medicine, $actor);
        $otherLot = $this->createLot($otherMedicine, $actor);
        $this->createMovement($medicine, $lot, $actor, 'receipt', 20, '2026-09-17T23:59:00Z');
        $morning = $this->createMovement($medicine, $lot, $actor, 'adjustment', -1, '2026-09-18T08:00:00Z', 'stock_count');
        $latest = $this->createMovement($medicine, $lot, $actor, 'adjustment', 2, '2026-09-18T09:00:00Z', 'correction');
        $this->createMovement($otherMedicine, $otherLot, $actor, 'receipt', 4, '2026-09-18T10:00:00Z');

        $firstPage = $this->getPayload('/api/v1/inventory/movements?medicineId='.$medicine->getKey().'&lotId='.$lot->getKey().'&movementType=adjustment&from=2026-09-18&to=2026-09-18&perPage=1');

        $this->assertSame([$latest->getKey()], array_column($firstPage['data'], 'movementId'));
        $this->assertSame(['page' => 1, 'perPage' => 1, 'total' => 2], $firstPage['meta']);
        $this->assertSame([
            'movementId' => $latest->getKey(),
            'medicineId' => $medicine->getKey(),
            'genericName' => 'Amoxicillin',
            'lotId' => $lot->getKey(),
            'actorUserId' => $actor->getKey(),
            'actorFullName' => 'Inventory Staff',
            'movementType' => 'adjustment',
            'quantityDelta' => 2,
            'reason' => 'correction',
            'sourceType' => null,
            'sourceId' => null,
            'occurredAt' => '2026-09-18T09:00:00+00:00',
        ], $firstPage['data'][0]);

        $secondPage = $this->getPayload('/api/v1/inventory/movements?medicineId='.$medicine->getKey().'&movementType=adjustment&from=2026-09-18&to=2026-09-18&perPage=1&page=2');
        $this->assertSame([$morning->getKey()], array_column($secondPage['data'], 'movementId'));
        $this->assertSame(2, $secondPage['meta']['page']);
    }

    public function test_it_rejects_invalid_movement_filters_with_an_encrypted_validation_error(): void
    {
        $path = '/api/v1/inventory/movements?movementType=unknown&from=2026-09-20&to=2026-09-18&perPage=101';
        $request = (new EncryptedTransportRequestBuilder)->buildBodyless();
        $response = $this->call('GET', $path, [], [], [], [
            'HTTP_X_TRANSPORT_KEY' => $request['header'],
        ])->assertUnprocessable();
        $body = (new EncryptedTransportResponseReader)
            ->read($response, $request['aesKey'], 'GET', $path)['body'];
        $error = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('validation_failed', $error['error']['code']);
        $this->assertArrayHasKey('movementType', $error['error']['details']);
        $this->assertArrayHasKey('to', $error['error']['details']);
        $this->assertArrayHasKey('perPage', $error['error']['details']);
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

    private function createLot(Medicine $medicine, User $actor): InventoryLot
    {
        $receipt = InventoryReceipt::query()->create([
            'actor_user_id' => $actor->getKey(),
            'received_at' => '2026-09-18T02:00:00Z',
        ]);

        return InventoryLot::query()->create([
            'receipt_id' => $receipt->getKey(),
            'medicine_id' => $medicine->getKey(),
            'received_at' => '2026-09-18T02:00:00Z',
            'expires_at' => '2028-06-30',
            'quantity_received' => 20,
            'quantity_remaining' => 20,
        ]);
    }

    private function createMovement(
        Medicine $medicine,
        InventoryLot $lot,
        User $actor,
        string $type,
        int $delta,
        string $occurredAt,
        ?string $reason = null,
    ): InventoryMovement {
        return InventoryMovement::query()->create([
            'medicine_id' => $medicine->getKey(),
            'inventory_lot_id' => $lot->getKey(),
            'actor_user_id' => $actor->getKey(),
            'movement_type' => $type,
            'quantity_delta' => $delta,
            'reason' => $reason,
            'occurred_at' => $occurredAt,
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
