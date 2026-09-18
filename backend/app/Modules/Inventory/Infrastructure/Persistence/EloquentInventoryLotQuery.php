<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence;

use App\Modules\Inventory\Application\InventoryLotFilters;
use App\Modules\Inventory\Application\InventoryLotItem;
use App\Modules\Inventory\Application\InventoryLotPage;
use App\Modules\Inventory\Application\InventoryLotQuery;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;
use stdClass;

final class EloquentInventoryLotQuery implements InventoryLotQuery
{
    public function search(InventoryLotFilters $filters): InventoryLotPage
    {
        $query = DB::table('inventory_lots')
            ->join('medicines', 'medicines.id', '=', 'inventory_lots.medicine_id')
            ->select([
                'inventory_lots.id AS lot_id',
                'inventory_lots.medicine_id',
                'medicines.generic_name',
                'medicines.brand_name',
                'inventory_lots.received_at',
                'inventory_lots.expires_at',
                'inventory_lots.quantity_received',
                'inventory_lots.quantity_remaining',
            ]);

        if ($filters->medicineId !== null) {
            $query->where('inventory_lots.medicine_id', $filters->medicineId);
        }

        if ($filters->expiresBefore !== null) {
            $query->whereDate('inventory_lots.expires_at', '<=', $filters->expiresBefore->format('Y-m-d'));
        }

        $this->applyAvailabilityFilter($query, $filters->available);

        $paginated = $query
            ->orderBy('inventory_lots.expires_at')
            ->orderBy('inventory_lots.received_at')
            ->orderBy('inventory_lots.id')
            ->paginate($filters->perPage, ['*'], 'page', $filters->page);
        $items = [];

        foreach ($paginated->items() as $row) {
            if (! $row instanceof stdClass) {
                throw new LogicException('The inventory lot query returned an invalid row.');
            }

            $items[] = $this->mapItem($row);
        }

        return new InventoryLotPage($items, $paginated->currentPage(), $paginated->perPage(), $paginated->total());
    }

    private function applyAvailabilityFilter(Builder $query, ?bool $available): void
    {
        if ($available === null) {
            return;
        }

        $today = now('UTC')->toDateString();

        if ($available) {
            $query->where('inventory_lots.quantity_remaining', '>', 0)
                ->whereDate('inventory_lots.expires_at', '>=', $today);

            return;
        }

        $query->where(static function (Builder $unavailable) use ($today): void {
            $unavailable->where('inventory_lots.quantity_remaining', '<=', 0)
                ->orWhereDate('inventory_lots.expires_at', '<', $today);
        });
    }

    private function mapItem(stdClass $row): InventoryLotItem
    {
        $quantityRemaining = $this->integerField($row->quantity_remaining);

        return new InventoryLotItem(
            $this->integerField($row->lot_id),
            $this->integerField($row->medicine_id),
            $this->stringField($row->generic_name),
            $this->nullableStringField($row->brand_name),
            $this->timestampField($row->received_at),
            $this->dateField($row->expires_at),
            $this->integerField($row->quantity_received),
            $quantityRemaining,
            $quantityRemaining > 0 && $row->expires_at >= now('UTC')->toDateString(),
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

        throw new LogicException('The inventory lot query returned an invalid integer.');
    }

    private function stringField(mixed $value): string
    {
        if (! is_string($value)) {
            throw new LogicException('The inventory lot query returned an invalid text value.');
        }

        return $value;
    }

    private function nullableStringField(mixed $value): ?string
    {
        return $value === null ? null : $this->stringField($value);
    }

    private function timestampField(mixed $value): string
    {
        if (! is_string($value)) {
            throw new LogicException('The inventory lot query returned an invalid timestamp.');
        }

        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:sP');
    }

    private function dateField(mixed $value): string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T].*)?$/D', $value) !== 1) {
            throw new LogicException('The inventory lot query returned an invalid expiry date.');
        }

        $date = new DateTimeImmutable(substr($value, 0, 10), new DateTimeZone('UTC'));

        return $date->format('Y-m-d');
    }
}
