<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence;

use App\Modules\Inventory\Application\InventorySummaryFilters;
use App\Modules\Inventory\Application\InventorySummaryItem;
use App\Modules\Inventory\Application\InventorySummaryPage;
use App\Modules\Inventory\Application\InventorySummaryQuery;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;
use stdClass;

final class EloquentInventorySummaryQuery implements InventorySummaryQuery
{
    public function search(InventorySummaryFilters $filters): InventorySummaryPage
    {
        $threshold = config('inventory.low_stock_threshold', 30);

        if (! is_int($threshold) || $threshold < 1) {
            throw new LogicException('The configured low-stock threshold must be a positive integer.');
        }

        $stocks = DB::table('inventory_lots')
            ->select('medicine_id')
            ->selectRaw('SUM(quantity_remaining) AS stock_on_hand')
            ->selectRaw('MIN(expires_at) AS earliest_expiry')
            ->where('quantity_remaining', '>', 0)
            ->groupBy('medicine_id');

        $query = DB::table('medicines')
            ->leftJoinSub($stocks, 'stock_summary', 'stock_summary.medicine_id', '=', 'medicines.id')
            ->where('medicines.active', true)
            ->select([
                'medicines.id AS medicine_id',
                'medicines.generic_name',
                'medicines.brand_name',
                'medicines.dosage_form',
                'medicines.strength',
                'medicines.unit_price',
                'stock_summary.stock_on_hand',
                'stock_summary.earliest_expiry',
            ]);

        $this->applyLowStockFilter($query, $filters->lowStock, $threshold);
        $this->applyExpiryFilter($query, $filters->expiresBefore);

        $paginated = $query
            ->orderBy('medicines.generic_name')
            ->orderBy('medicines.id')
            ->paginate($filters->perPage, ['*'], 'page', $filters->page);
        $items = [];

        foreach ($paginated->items() as $row) {
            if (! $row instanceof stdClass) {
                throw new LogicException('The inventory summary query returned an invalid row.');
            }

            $items[] = $this->mapItem($row, $threshold);
        }

        return new InventorySummaryPage($items, $paginated->currentPage(), $paginated->perPage(), $paginated->total());
    }

    private function applyLowStockFilter(Builder $query, ?bool $lowStock, int $threshold): void
    {
        if ($lowStock === null) {
            return;
        }

        $operator = $lowStock ? '<' : '>=';
        $query->whereRaw('COALESCE(stock_summary.stock_on_hand, 0) '.$operator.' ?', [$threshold]);
    }

    private function applyExpiryFilter(Builder $query, ?DateTimeImmutable $expiresBefore): void
    {
        if ($expiresBefore === null) {
            return;
        }

        $query->whereExists(static function (Builder $subquery) use ($expiresBefore): void {
            $subquery->selectRaw('1')
                ->from('inventory_lots as expiring_lots')
                ->whereColumn('expiring_lots.medicine_id', 'medicines.id')
                ->where('expiring_lots.quantity_remaining', '>', 0)
                ->whereDate('expiring_lots.expires_at', '<=', $expiresBefore->format('Y-m-d'));
        });
    }

    private function mapItem(stdClass $row, int $threshold): InventorySummaryItem
    {
        $stockOnHand = $this->integerField($row->stock_on_hand ?? null) ?? 0;

        return new InventorySummaryItem(
            $this->integerField($row->medicine_id) ?? throw new LogicException('A medicine row has no identifier.'),
            $this->stringField($row->generic_name),
            $this->nullableStringField($row->brand_name ?? null),
            $this->stringField($row->dosage_form),
            $this->nullableStringField($row->strength ?? null),
            $this->decimalField($row->unit_price),
            $stockOnHand,
            $stockOnHand < $threshold,
            $this->dateField($row->earliest_expiry ?? null),
        );
    }

    private function integerField(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new LogicException('The inventory summary query returned an invalid integer.');
    }

    private function stringField(mixed $value): string
    {
        if (! is_string($value)) {
            throw new LogicException('The inventory summary query returned an invalid text value.');
        }

        return $value;
    }

    private function nullableStringField(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->stringField($value);
    }

    private function decimalField(mixed $value): string
    {
        if (is_float($value)) {
            return number_format($value, 2, '.', '');
        }

        if (is_int($value)) {
            return $value.'.00';
        }

        if (! is_string($value) || preg_match('/^(\d+)(?:\.(\d{1,2}))?$/D', $value, $matches) !== 1) {
            throw new LogicException('The inventory summary query returned an invalid unit price.');
        }

        $whole = ltrim($matches[1], '0');
        $fraction = str_pad($matches[2] ?? '', 2, '0');

        return ($whole === '' ? '0' : $whole).'.'.$fraction;
    }

    private function dateField(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value) || preg_match('/^(\d{4}-\d{2}-\d{2})(?:[ T].*)?$/D', $value, $matches) !== 1) {
            throw new LogicException('The inventory summary query returned an invalid expiry date.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $matches[1]);

        if ($date === false) {
            throw new LogicException('The inventory summary query returned an invalid expiry date.');
        }

        return $date->format('Y-m-d');
    }
}
