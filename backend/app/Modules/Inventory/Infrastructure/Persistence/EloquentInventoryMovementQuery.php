<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence;

use App\Modules\Inventory\Application\InventoryMovementFilters;
use App\Modules\Inventory\Application\InventoryMovementItem;
use App\Modules\Inventory\Application\InventoryMovementPage;
use App\Modules\Inventory\Application\InventoryMovementQuery;
use DateTimeZone;
use LogicException;

final class EloquentInventoryMovementQuery implements InventoryMovementQuery
{
    public function search(InventoryMovementFilters $filters): InventoryMovementPage
    {
        $query = InventoryMovement::query()
            ->with([
                'medicine:id,generic_name',
                'lot:id',
                'actor:id,full_name_encrypted',
            ]);

        if ($filters->medicineId !== null) {
            $query->where('medicine_id', $filters->medicineId);
        }

        if ($filters->lotId !== null) {
            $query->where('inventory_lot_id', $filters->lotId);
        }

        if ($filters->movementType !== null) {
            $query->where('movement_type', $filters->movementType);
        }

        if ($filters->from !== null) {
            $query->whereDate('occurred_at', '>=', $filters->from->format('Y-m-d'));
        }

        if ($filters->to !== null) {
            $query->whereDate('occurred_at', '<=', $filters->to->format('Y-m-d'));
        }

        $paginated = $query
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($filters->perPage, ['*'], 'page', $filters->page);
        $items = [];

        foreach ($paginated->items() as $movement) {
            $items[] = $this->mapItem($movement);
        }

        return new InventoryMovementPage(
            $items,
            $paginated->currentPage(),
            $paginated->perPage(),
            $paginated->total(),
        );
    }

    private function mapItem(InventoryMovement $movement): InventoryMovementItem
    {
        $medicine = $movement->medicine;
        $lot = $movement->lot;
        $actor = $movement->actor;
        $occurredAt = $movement->occurred_at;

        if ($medicine === null || $lot === null || $actor === null) {
            throw new LogicException('An inventory movement is missing its required references.');
        }

        return new InventoryMovementItem(
            $this->integerField($movement->getKey()),
            $this->integerField($movement->medicine_id),
            $medicine->generic_name,
            $this->integerField($movement->inventory_lot_id),
            $this->integerField($movement->actor_user_id),
            $actor->fullName()->value,
            $movement->movement_type,
            $movement->quantity_delta,
            $movement->reason,
            $movement->source_type,
            $this->nullableIntegerField($movement->source_id),
            $occurredAt
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:sP'),
        );
    }

    private function integerField(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new LogicException('The inventory movement query returned an invalid integer.');
    }

    private function nullableIntegerField(mixed $value): ?int
    {
        return $value === null ? null : $this->integerField($value);
    }
}
