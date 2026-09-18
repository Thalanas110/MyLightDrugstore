<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence;

use App\Modules\Catalog\Application\MedicineListFilters;
use App\Modules\Catalog\Application\MedicineListItem;
use App\Modules\Catalog\Application\MedicineListPage;
use App\Modules\Catalog\Application\MedicineListQuery;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

final class EloquentMedicineListQuery implements MedicineListQuery
{
    public function search(MedicineListFilters $filters): MedicineListPage
    {
        $threshold = config('inventory.low_stock_threshold', 30);

        if (! is_int($threshold) || $threshold < 1) {
            throw new LogicException('The configured low-stock threshold must be a positive integer.');
        }

        $today = now('UTC')->toDateString();
        $stocks = DB::table('inventory_lots')
            ->select('medicine_id')
            ->selectRaw('SUM(quantity_remaining) AS stock_on_hand')
            ->selectRaw('MIN(expires_at) AS earliest_expiry')
            ->where('quantity_remaining', '>', 0)
            ->whereDate('expires_at', '>=', $today)
            ->groupBy('medicine_id');

        $query = Medicine::query()
            ->leftJoinSub($stocks, 'stock_summary', 'stock_summary.medicine_id', '=', 'medicines.id')
            ->select([
                'medicines.*',
                DB::raw('COALESCE(stock_summary.stock_on_hand, 0) AS stock_on_hand'),
            ])
            ->where('medicines.active', $filters->active);

        if ($filters->query !== null) {
            $searchTerm = '%'.$filters->query.'%';
            $query->where(static function (EloquentBuilder $search) use ($searchTerm): void {
                $search->where('medicines.generic_name', 'like', $searchTerm)
                    ->orWhere('medicines.brand_name', 'like', $searchTerm)
                    ->orWhere('medicines.dosage_form', 'like', $searchTerm)
                    ->orWhere('medicines.strength', 'like', $searchTerm);
            });
        }

        $this->applyLowStockFilter($query, $filters->lowStock, $threshold);
        $this->applyExpiryFilter($query, $filters->expiresBefore, $today);
        $paginated = $query
            ->orderBy('medicines.generic_name')
            ->orderBy('medicines.id')
            ->paginate($filters->perPage, ['*'], 'page', $filters->page);
        $items = [];

        foreach ($paginated->items() as $medicine) {
            $items[] = $this->mapItem($medicine);
        }

        return new MedicineListPage($items, $paginated->currentPage(), $paginated->perPage(), $paginated->total());
    }

    /** @param EloquentBuilder<Medicine> $query */
    private function applyLowStockFilter(EloquentBuilder $query, ?bool $lowStock, int $threshold): void
    {
        if ($lowStock === null) {
            return;
        }

        $operator = $lowStock ? '<' : '>=';
        $query->whereRaw('COALESCE(stock_summary.stock_on_hand, 0) '.$operator.' ?', [$threshold]);
    }

    /** @param EloquentBuilder<Medicine> $query */
    private function applyExpiryFilter(EloquentBuilder $query, ?DateTimeImmutable $expiresBefore, string $today): void
    {
        if ($expiresBefore === null) {
            return;
        }

        $query->whereExists(static function (Builder $subquery) use ($expiresBefore, $today): void {
            $subquery->selectRaw('1')
                ->from('inventory_lots as expiring_lots')
                ->whereColumn('expiring_lots.medicine_id', 'medicines.id')
                ->where('expiring_lots.quantity_remaining', '>', 0)
                ->whereDate('expiring_lots.expires_at', '>=', $today)
                ->whereDate('expiring_lots.expires_at', '<=', $expiresBefore->format('Y-m-d'));
        });
    }

    private function mapItem(Medicine $medicine): MedicineListItem
    {
        return new MedicineListItem(
            $this->integerField($medicine->getKey()),
            $this->stringField($medicine->generic_name),
            $this->nullableStringField($medicine->brand_name),
            $this->nullableStringField($medicine->description),
            $this->stringField($medicine->dosage_form),
            $this->nullableStringField($medicine->strength),
            $this->decimalField($medicine->unit_price),
            $this->nullableStringField($medicine->storage_location),
            $medicine->active,
            $this->integerField($medicine->getAttribute('stock_on_hand')),
            $this->timestampField($medicine->created_at),
            $this->timestampField($medicine->updated_at),
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

        throw new LogicException('The medicine list query returned an invalid integer.');
    }

    private function stringField(mixed $value): string
    {
        if (! is_string($value)) {
            throw new LogicException('The medicine list query returned an invalid text value.');
        }

        return $value;
    }

    private function nullableStringField(mixed $value): ?string
    {
        return $value === null ? null : $this->stringField($value);
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
            throw new LogicException('The medicine list query returned an invalid unit price.');
        }

        $whole = ltrim($matches[1], '0');
        $fraction = str_pad($matches[2] ?? '', 2, '0');

        return ($whole === '' ? '0' : $whole).'.'.$fraction;
    }

    private function timestampField(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof DateTimeInterface) {
            throw new LogicException('The medicine list query returned an invalid timestamp.');
        }

        return DateTimeImmutable::createFromInterface($value)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z');
    }
}
