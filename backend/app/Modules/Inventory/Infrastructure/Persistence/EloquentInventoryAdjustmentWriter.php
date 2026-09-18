<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence;

use App\Modules\Inventory\Application\InventoryAdjustmentCommand;
use App\Modules\Inventory\Application\InventoryAdjustmentResult;
use App\Modules\Inventory\Application\InventoryAdjustmentWriter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class EloquentInventoryAdjustmentWriter implements InventoryAdjustmentWriter
{
    private const int MAX_LOT_QUANTITY = 4_294_967_295;

    public function adjust(InventoryAdjustmentCommand $command): InventoryAdjustmentResult
    {
        return DB::transaction(function () use ($command): InventoryAdjustmentResult {
            $lotIds = array_map(static fn ($item): int => $item->lotId, $command->items);
            $lots = InventoryLot::query()
                ->whereKey($lotIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($lots->count() !== count($lotIds)) {
                throw (new ModelNotFoundException)->setModel(InventoryLot::class);
            }

            $nextQuantities = [];

            foreach ($command->items as $item) {
                $lot = $lots->get($item->lotId);

                if (! $lot instanceof InventoryLot) {
                    throw new LogicException('The locked inventory lot could not be retrieved.');
                }

                $nextQuantity = $lot->quantity_remaining + $item->quantityDelta;

                if ($nextQuantity < 0 || $nextQuantity > self::MAX_LOT_QUANTITY) {
                    throw new HttpException(409, 'The adjustment is outside the lot quantity range.');
                }

                $nextQuantities[$item->lotId] = $nextQuantity;
            }

            $occurredAt = now('UTC')->toImmutable();
            $adjustment = InventoryAdjustment::query()->create([
                'actor_user_id' => $command->actorUserId,
                'reason' => $command->reason->value,
                'occurred_at' => $occurredAt,
            ]);
            $adjustmentId = $adjustment->getKey();

            if (! is_int($adjustmentId)) {
                throw new LogicException('The inventory adjustment was created without a valid identifier.');
            }

            foreach ($command->items as $item) {
                $lot = $lots->get($item->lotId);

                if (! $lot instanceof InventoryLot) {
                    throw new LogicException('The locked inventory lot could not be retrieved.');
                }

                $lot->quantity_remaining = $nextQuantities[$item->lotId];
                $lot->save();

                InventoryMovement::query()->create([
                    'medicine_id' => $lot->medicine_id,
                    'inventory_lot_id' => $item->lotId,
                    'actor_user_id' => $command->actorUserId,
                    'movement_type' => 'adjustment',
                    'quantity_delta' => $item->quantityDelta,
                    'reason' => $command->reason->value,
                    'source_type' => 'inventory_adjustment',
                    'source_id' => $adjustmentId,
                    'occurred_at' => $occurredAt,
                ]);
            }

            return new InventoryAdjustmentResult(
                $adjustmentId,
                $command->reason,
                count($command->items),
                array_sum(array_map(static fn ($item): int => $item->quantityDelta, $command->items)),
            );
        });
    }
}
