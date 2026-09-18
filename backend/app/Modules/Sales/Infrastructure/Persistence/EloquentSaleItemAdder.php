<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure\Persistence;

use App\Modules\Catalog\Application\SaleMedicinePriceQuery;
use App\Modules\Inventory\Application\InventoryStockReserver;
use App\Modules\Inventory\Application\ReserveSaleStockItem;
use App\Modules\Inventory\Application\ReserveStockForSaleCommand;
use App\Modules\Sales\Application\AddSaleItemCommand;
use App\Modules\Sales\Application\SaleDetails;
use App\Modules\Sales\Application\SaleDetailsQuery;
use App\Modules\Sales\Application\SaleItemAdder;
use App\Modules\Sales\Domain\IdempotencyKeyReusedException;
use App\Modules\Sales\Domain\SaleAmount;
use App\Modules\Sales\Domain\SaleNotEditableException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use LogicException;

final class EloquentSaleItemAdder implements SaleItemAdder
{
    private const string IDEMPOTENCY_OPERATION = 'sales.add_item';

    public function __construct(
        private readonly SaleMedicinePriceQuery $saleMedicinePriceQuery,
        private readonly InventoryStockReserver $inventoryStockReserver,
        private readonly SaleDetailsQuery $saleDetailsQuery,
    ) {}

    public function add(AddSaleItemCommand $command): SaleDetails
    {
        return DB::transaction(function () use ($command): SaleDetails {
            $requestHash = hash('sha256', json_encode([
                'saleId' => $command->saleId,
                'medicineId' => $command->medicineId,
                'quantity' => $command->quantity,
            ], JSON_THROW_ON_ERROR));
            $idempotencyKeyHash = hash('sha256', $command->idempotencyKey);
            $now = now('UTC');

            DB::table('sales_idempotency_keys')->insertOrIgnore([
                'actor_user_id' => $command->actorUserId,
                'operation' => self::IDEMPOTENCY_OPERATION,
                'idempotency_key_hash' => $idempotencyKeyHash,
                'request_hash' => $requestHash,
                'response_body' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $idempotencyRecord = DB::table('sales_idempotency_keys')
                ->where('actor_user_id', $command->actorUserId)
                ->where('operation', self::IDEMPOTENCY_OPERATION)
                ->where('idempotency_key_hash', $idempotencyKeyHash)
                ->lockForUpdate()
                ->first();

            if ($idempotencyRecord === null || ! is_string($idempotencyRecord->request_hash)) {
                throw new LogicException('The sale item idempotency record could not be loaded.');
            }

            if (! hash_equals($idempotencyRecord->request_hash, $requestHash)) {
                throw new IdempotencyKeyReusedException;
            }

            if (is_string($idempotencyRecord->response_body)) {
                return SaleDetails::fromArray(json_decode(
                    $idempotencyRecord->response_body,
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                ));
            }

            $sale = Sale::query()
                ->whereKey($command->saleId)
                ->lockForUpdate()
                ->firstOrFail();
            $state = $sale->getAttribute('state');
            $paymentStatus = $sale->getAttribute('payment_status');

            if (! is_string($state) || ! is_string($paymentStatus)) {
                throw new LogicException('The sale persistence model contains invalid state data.');
            }

            if ($state !== 'open' || $paymentStatus !== 'unpaid') {
                throw new SaleNotEditableException;
            }

            $unitPrice = $this->saleMedicinePriceQuery->findActivePrices([$command->medicineId])[$command->medicineId] ?? null;

            if (! is_string($unitPrice)) {
                throw new ModelNotFoundException;
            }

            $lineTotal = SaleAmount::fromDecimal($unitPrice)->multiply($command->quantity);
            $storedTotal = $sale->getAttribute('total');

            if (! is_string($storedTotal)) {
                throw new LogicException('The sale persistence model contains an invalid total.');
            }

            $saleTotal = SaleAmount::fromDecimal($storedTotal)->add($lineTotal);

            SaleItem::query()->create([
                'sale_id' => $command->saleId,
                'medicine_id' => $command->medicineId,
                'quantity' => $command->quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal->toDecimal(),
                'state' => 'active',
            ]);
            $sale->setAttribute('total', $saleTotal->toDecimal());
            $sale->save();

            $this->inventoryStockReserver->reserveForSale(new ReserveStockForSaleCommand(
                $command->actorUserId,
                $command->saleId,
                [new ReserveSaleStockItem($command->medicineId, $command->quantity)],
            ));

            $details = $this->saleDetailsQuery->find($command->saleId);

            if ($details === null) {
                throw new LogicException('The updated sale could not be reloaded.');
            }

            $updatedRows = DB::table('sales_idempotency_keys')
                ->where('actor_user_id', $command->actorUserId)
                ->where('operation', self::IDEMPOTENCY_OPERATION)
                ->where('idempotency_key_hash', $idempotencyKeyHash)
                ->update([
                    'response_body' => json_encode($details->toArray(), JSON_THROW_ON_ERROR),
                    'updated_at' => now('UTC'),
                ]);

            if ($updatedRows !== 1) {
                throw new LogicException('The sale item idempotency response could not be stored.');
            }

            return $details;
        });
    }
}
