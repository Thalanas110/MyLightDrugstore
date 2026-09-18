<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure\Persistence;

use App\Modules\Catalog\Application\SaleMedicinePriceQuery;
use App\Modules\Inventory\Application\InventoryStockReserver;
use App\Modules\Inventory\Application\ReserveSaleStockItem;
use App\Modules\Inventory\Application\ReserveStockForSaleCommand;
use App\Modules\Sales\Application\CreateSaleCommand;
use App\Modules\Sales\Application\CreateSaleItem;
use App\Modules\Sales\Application\SaleCreator;
use App\Modules\Sales\Application\SaleDetails;
use App\Modules\Sales\Domain\IdempotencyKeyReusedException;
use App\Modules\Sales\Domain\SaleAmount;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use LogicException;

final class EloquentSaleCreator implements SaleCreator
{
    private const string IDEMPOTENCY_OPERATION = 'sales.create';

    public function __construct(
        private readonly SaleMedicinePriceQuery $saleMedicinePriceQuery,
        private readonly InventoryStockReserver $inventoryStockReserver,
    ) {}

    public function create(CreateSaleCommand $command): SaleDetails
    {
        return DB::transaction(function () use ($command): SaleDetails {
            $itemRequest = array_map(
                static fn (CreateSaleItem $item): array => [
                    'medicineId' => $item->medicineId,
                    'quantity' => $item->quantity,
                ],
                $command->items,
            );
            $requestHash = hash('sha256', json_encode($itemRequest, JSON_THROW_ON_ERROR));
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
                throw new LogicException('The sale idempotency record could not be loaded.');
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

            $medicineIds = array_values(array_unique(array_map(
                static fn (CreateSaleItem $item): int => $item->medicineId,
                $command->items,
            )));
            sort($medicineIds);
            $unitPrices = $this->saleMedicinePriceQuery->findActivePrices($medicineIds);

            if (count($unitPrices) !== count($medicineIds)) {
                throw new ModelNotFoundException;
            }

            $saleItems = [];
            $total = SaleAmount::zero();

            foreach ($command->items as $item) {
                $unitPrice = $unitPrices[$item->medicineId] ?? null;

                if (! is_string($unitPrice)) {
                    throw new LogicException('The active medicine price query omitted a requested medicine.');
                }

                $lineTotal = SaleAmount::fromDecimal($unitPrice)->multiply($item->quantity);
                $total = $total->add($lineTotal);
                $saleItems[] = [
                    'medicineId' => $item->medicineId,
                    'quantity' => $item->quantity,
                    'unitPrice' => $unitPrice,
                    'lineTotal' => $lineTotal->toDecimal(),
                ];
            }

            $sale = Sale::query()->create([
                'created_by_user_id' => $command->actorUserId,
                'state' => 'open',
                'payment_status' => 'unpaid',
                'total' => $total->toDecimal(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $saleId = $sale->getKey();

            if (! is_int($saleId)) {
                throw new LogicException('The sale was created without a valid identifier.');
            }

            $stockItems = [];
            $responseItems = [];

            foreach ($saleItems as $item) {
                $saleItem = SaleItem::query()->create([
                    'sale_id' => $saleId,
                    'medicine_id' => $item['medicineId'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unitPrice'],
                    'line_total' => $item['lineTotal'],
                    'state' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $saleItemId = $saleItem->getKey();

                if (! is_int($saleItemId)) {
                    throw new LogicException('The sale item was created without a valid identifier.');
                }

                $responseItems[] = [
                    'id' => $saleItemId,
                    'medicineId' => $item['medicineId'],
                    'quantity' => $item['quantity'],
                    'unitPrice' => $item['unitPrice'],
                    'lineTotal' => $item['lineTotal'],
                    'state' => 'active',
                ];
                $stockItems[] = new ReserveSaleStockItem($item['medicineId'], $item['quantity'], $saleItemId);
            }

            $this->inventoryStockReserver->reserveForSale(new ReserveStockForSaleCommand(
                $command->actorUserId,
                $saleId,
                $stockItems,
            ));

            $details = $this->toSaleDetails(
                $sale,
                $command->actorUserId,
                $total->toDecimal(),
                $responseItems,
            );
            $responseBody = json_encode($details->toArray(), JSON_THROW_ON_ERROR);
            $updatedRows = DB::table('sales_idempotency_keys')
                ->where('actor_user_id', $command->actorUserId)
                ->where('operation', self::IDEMPOTENCY_OPERATION)
                ->where('idempotency_key_hash', $idempotencyKeyHash)
                ->update([
                    'response_body' => $responseBody,
                    'updated_at' => now('UTC'),
                ]);

            if ($updatedRows !== 1) {
                throw new LogicException('The sale idempotency response could not be stored.');
            }

            return $details;
        });
    }

    /**
     * @param  list<array{
     *     id: int,
     *     medicineId: int,
     *     quantity: int,
     *     unitPrice: string,
     *     lineTotal: string,
     *     state: string
     * }>  $items
     */
    private function toSaleDetails(Sale $sale, int $createdBy, string $total, array $items): SaleDetails
    {
        $saleId = $sale->getKey();
        $createdAt = $sale->getAttribute('created_at');

        if (
            ! is_int($saleId)
            || ! $createdAt instanceof DateTimeInterface
        ) {
            throw new LogicException('The sale persistence model contains invalid response data.');
        }

        $createdAt = DateTimeImmutable::createFromInterface($createdAt)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z');

        return new SaleDetails($saleId, $createdBy, $createdAt, 'open', 'unpaid', $total, $items);
    }
}
