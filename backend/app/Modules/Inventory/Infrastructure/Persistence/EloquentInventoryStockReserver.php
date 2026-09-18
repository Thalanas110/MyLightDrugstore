<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence;

use App\Modules\Inventory\Application\InventoryStockReserver;
use App\Modules\Inventory\Application\ReserveSaleStockItem;
use App\Modules\Inventory\Application\ReserveStockForSaleCommand;
use App\Modules\Inventory\Domain\InsufficientStockException;
use Illuminate\Support\Facades\DB;

final class EloquentInventoryStockReserver implements InventoryStockReserver
{
    public function reserveForSale(ReserveStockForSaleCommand $command): void
    {
        DB::transaction(function () use ($command): void {
            $medicineIds = array_values(array_unique(array_map(
                static fn (ReserveSaleStockItem $item): int => $item->medicineId,
                $command->items,
            )));
            sort($medicineIds);

            $lotsByMedicine = [];

            foreach (InventoryLot::query()
                ->whereIn('medicine_id', $medicineIds)
                ->where('quantity_remaining', '>', 0)
                ->whereDate('expires_at', '>=', now('UTC')->toDateString())
                ->orderBy('medicine_id')
                ->orderBy('expires_at')
                ->orderBy('received_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get() as $lot) {
                $medicineId = $lot->medicine_id;
                $lotsByMedicine[$medicineId][] = $lot;
            }

            /** @var list<array{lot: InventoryLot, quantity: int}> $allocations */
            $allocations = [];

            foreach ($command->items as $item) {
                $quantityRemaining = $item->quantity;

                foreach ($lotsByMedicine[$item->medicineId] ?? [] as $lot) {
                    $lotQuantity = $lot->quantity_remaining;
                    $allocatedQuantity = min($lotQuantity, $quantityRemaining);

                    if ($allocatedQuantity < 1) {
                        continue;
                    }

                    $allocations[] = ['lot' => $lot, 'quantity' => $allocatedQuantity];
                    $quantityRemaining -= $allocatedQuantity;

                    if ($quantityRemaining === 0) {
                        break;
                    }
                }

                if ($quantityRemaining > 0) {
                    throw new InsufficientStockException;
                }
            }

            foreach ($allocations as $allocation) {
                $lot = $allocation['lot'];
                $quantity = $allocation['quantity'];
                $remainingQuantity = $lot->quantity_remaining - $quantity;

                if ($remainingQuantity < 0) {
                    throw new InsufficientStockException;
                }

                $lot->quantity_remaining = $remainingQuantity;
                $lot->save();

                InventoryMovement::query()->create([
                    'medicine_id' => $lot->medicine_id,
                    'inventory_lot_id' => $lot->getKey(),
                    'actor_user_id' => $command->actorUserId,
                    'movement_type' => 'sale',
                    'quantity_delta' => -$quantity,
                    'source_type' => 'sale',
                    'source_id' => $command->saleId,
                    'occurred_at' => now('UTC'),
                ]);
            }
        });
    }
}
