<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure\Persistence;

use App\Modules\Inventory\Application\InventoryStockRestorer;
use App\Modules\Inventory\Application\RestoreSaleItemStockCommand;
use App\Modules\Sales\Application\RemoveSaleItemCommand;
use App\Modules\Sales\Application\SaleDetails;
use App\Modules\Sales\Application\SaleDetailsQuery;
use App\Modules\Sales\Application\SaleItemRemover;
use App\Modules\Sales\Domain\SaleAmount;
use App\Modules\Sales\Domain\SaleNotEditableException;
use Illuminate\Support\Facades\DB;
use LogicException;

final class EloquentSaleItemRemover implements SaleItemRemover
{
    public function __construct(
        private readonly InventoryStockRestorer $inventoryStockRestorer,
        private readonly SaleDetailsQuery $saleDetailsQuery,
    ) {}

    public function remove(RemoveSaleItemCommand $command): SaleDetails
    {
        return DB::transaction(function () use ($command): SaleDetails {
            $sale = Sale::query()
                ->whereKey($command->saleId)
                ->lockForUpdate()
                ->firstOrFail();
            $saleState = $sale->getAttribute('state');
            $paymentStatus = $sale->getAttribute('payment_status');

            if (! is_string($saleState) || ! is_string($paymentStatus)) {
                throw new LogicException('The sale persistence model contains invalid state data.');
            }

            if ($saleState !== 'open' || $paymentStatus !== 'unpaid') {
                throw new SaleNotEditableException;
            }

            $saleItem = SaleItem::query()
                ->where('sale_id', $command->saleId)
                ->whereKey($command->saleItemId)
                ->lockForUpdate()
                ->firstOrFail();
            $itemState = $saleItem->getAttribute('state');

            if (! is_string($itemState)) {
                throw new LogicException('The sale item persistence model contains invalid state data.');
            }

            if ($itemState === 'removed') {
                return $this->findSaleDetails($command->saleId);
            }

            if ($itemState !== 'active') {
                throw new SaleNotEditableException;
            }

            $medicineId = $saleItem->getAttribute('medicine_id');
            $quantity = $saleItem->getAttribute('quantity');
            $lineTotal = $saleItem->getAttribute('line_total');
            $storedTotal = $sale->getAttribute('total');

            if (
                ! is_int($medicineId)
                || ! is_int($quantity)
                || ! is_string($lineTotal)
                || ! is_string($storedTotal)
            ) {
                throw new LogicException('The sale item persistence model contains invalid amount data.');
            }

            $saleTotal = SaleAmount::fromDecimal($storedTotal)
                ->subtract(SaleAmount::fromDecimal($lineTotal));

            $this->inventoryStockRestorer->restoreSaleItemStock(new RestoreSaleItemStockCommand(
                $command->actorUserId,
                $command->saleId,
                $command->saleItemId,
                $medicineId,
                $quantity,
            ));

            $saleItem->setAttribute('state', 'removed');
            $saleItem->save();
            $sale->setAttribute('total', $saleTotal->toDecimal());
            $sale->save();

            return $this->findSaleDetails($command->saleId);
        });
    }

    private function findSaleDetails(int $saleId): SaleDetails
    {
        $sale = $this->saleDetailsQuery->find($saleId);

        if ($sale === null) {
            throw new LogicException('The updated sale could not be reloaded.');
        }

        return $sale;
    }
}
