<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence;

use App\Modules\Inventory\Application\InventoryStockRestorer;
use App\Modules\Inventory\Application\RestoreSaleItemStockCommand;
use App\Modules\Inventory\Domain\SaleItemStockHistoryMissingException;
use Illuminate\Support\Facades\DB;
use LogicException;

final class EloquentInventoryStockRestorer implements InventoryStockRestorer
{
    public function restoreSaleItemStock(RestoreSaleItemStockCommand $command): void
    {
        DB::transaction(function () use ($command): void {
            $movements = InventoryMovement::query()
                ->where('sale_item_id', $command->saleItemId)
                ->where('movement_type', 'sale')
                ->where('source_type', 'sale')
                ->where('source_id', $command->saleId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            /** @var array<int, int> $quantityByLot */
            $quantityByLot = [];

            foreach ($movements as $movement) {
                $medicineId = $movement->getAttribute('medicine_id');
                $lotId = $movement->getAttribute('inventory_lot_id');
                $quantityDelta = $movement->getAttribute('quantity_delta');

                if (
                    ! is_int($medicineId)
                    || $medicineId !== $command->medicineId
                    || ! is_int($lotId)
                    || ! is_int($quantityDelta)
                    || $quantityDelta >= 0
                ) {
                    throw new SaleItemStockHistoryMissingException;
                }

                $quantityByLot[$lotId] = ($quantityByLot[$lotId] ?? 0) - $quantityDelta;
            }

            if ($quantityByLot === [] || array_sum($quantityByLot) !== $command->quantity) {
                throw new SaleItemStockHistoryMissingException;
            }

            $lotsById = [];

            foreach (InventoryLot::query()
                ->whereKey(array_keys($quantityByLot))
                ->orderBy('id')
                ->lockForUpdate()
                ->get() as $lot) {
                $lotId = $lot->getKey();

                if (! is_int($lotId)) {
                    throw new LogicException('The inventory lot has an invalid identifier.');
                }

                $lotsById[$lotId] = $lot;
            }

            if (count($lotsById) !== count($quantityByLot)) {
                throw new SaleItemStockHistoryMissingException;
            }

            foreach ($quantityByLot as $lotId => $quantity) {
                $lot = $lotsById[$lotId] ?? null;

                if ($lot === null) {
                    throw new SaleItemStockHistoryMissingException;
                }

                $medicineId = $lot->getAttribute('medicine_id');
                $remainingQuantity = $lot->getAttribute('quantity_remaining');
                $receivedQuantity = $lot->getAttribute('quantity_received');

                if (
                    ! is_int($medicineId)
                    || $medicineId !== $command->medicineId
                    || ! is_int($remainingQuantity)
                    || ! is_int($receivedQuantity)
                    || $remainingQuantity + $quantity > $receivedQuantity
                ) {
                    throw new SaleItemStockHistoryMissingException;
                }

                $lot->setAttribute('quantity_remaining', $remainingQuantity + $quantity);
                $lot->save();

                InventoryMovement::query()->create([
                    'medicine_id' => $command->medicineId,
                    'inventory_lot_id' => $lotId,
                    'sale_item_id' => $command->saleItemId,
                    'actor_user_id' => $command->actorUserId,
                    'movement_type' => 'sale_item_removal',
                    'quantity_delta' => $quantity,
                    'source_type' => 'sale',
                    'source_id' => $command->saleId,
                    'occurred_at' => now('UTC'),
                ]);
            }
        });
    }
}
