<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence;

use App\Modules\Catalog\Infrastructure\Persistence\Medicine;
use App\Modules\Inventory\Application\InventoryReceiptResult;
use App\Modules\Inventory\Application\InventoryReceiptWriter;
use App\Modules\Inventory\Application\ReceiveStockCommand;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use LogicException;

final class EloquentInventoryReceiptWriter implements InventoryReceiptWriter
{
    public function receive(ReceiveStockCommand $command): InventoryReceiptResult
    {
        return DB::transaction(function () use ($command): InventoryReceiptResult {
            $medicineIds = array_values(array_unique(array_map(
                static fn ($item): int => $item->medicineId,
                $command->items,
            )));
            $activeMedicineIds = Medicine::query()
                ->whereKey($medicineIds)
                ->where('active', true)
                ->lockForUpdate()
                ->pluck('id')
                ->map(static function (mixed $id): int {
                    if (! is_int($id) && (! is_string($id) || ! ctype_digit($id))) {
                        throw new LogicException('The medicine query returned an invalid identifier.');
                    }

                    return (int) $id;
                })
                ->all();

            if (count($activeMedicineIds) !== count($medicineIds)) {
                throw (new ModelNotFoundException)->setModel(Medicine::class);
            }

            $receipt = InventoryReceipt::query()->create([
                'actor_user_id' => $command->actorUserId,
                'received_at' => $command->receivedAt,
            ]);
            $receiptId = $receipt->getKey();

            if (! is_int($receiptId)) {
                throw new LogicException('The inventory receipt was created without a valid identifier.');
            }

            foreach ($command->items as $item) {
                $lot = InventoryLot::query()->create([
                    'receipt_id' => $receiptId,
                    'medicine_id' => $item->medicineId,
                    'received_at' => $command->receivedAt,
                    'expires_at' => $item->expiresAt,
                    'quantity_received' => $item->quantity,
                    'quantity_remaining' => $item->quantity,
                ]);
                $lotId = $lot->getKey();

                if (! is_int($lotId)) {
                    throw new LogicException('The inventory lot was created without a valid identifier.');
                }

                InventoryMovement::query()->create([
                    'medicine_id' => $item->medicineId,
                    'inventory_lot_id' => $lotId,
                    'actor_user_id' => $command->actorUserId,
                    'movement_type' => 'receipt',
                    'quantity_delta' => $item->quantity,
                    'source_type' => 'inventory_receipt',
                    'source_id' => $receiptId,
                    'occurred_at' => $command->receivedAt,
                ]);
            }

            return new InventoryReceiptResult(
                $receiptId,
                count($command->items),
                array_sum(array_map(static fn ($item): int => $item->quantity, $command->items)),
                $command->receivedAt,
            );
        });
    }
}
