<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure\Persistence;

use App\Modules\Sales\Application\SaleListFilters;
use App\Modules\Sales\Application\SaleListItem;
use App\Modules\Sales\Application\SaleListPage;
use App\Modules\Sales\Application\SaleListQuery;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use LogicException;

final class EloquentSaleListQuery implements SaleListQuery
{
    public function search(SaleListFilters $filters): SaleListPage
    {
        $query = Sale::query()->withCount([
            'items' => static function (EloquentBuilder $query): void {
                $query->where('state', 'active');
            },
        ]);

        if ($filters->paymentStatus !== null) {
            $query->where('payment_status', $filters->paymentStatus);
        }

        if ($filters->state !== null) {
            $query->where('state', $filters->state);
        }

        if ($filters->from !== null) {
            $query->whereDate('created_at', '>=', $filters->from->format('Y-m-d'));
        }

        if ($filters->to !== null) {
            $query->whereDate('created_at', '<=', $filters->to->format('Y-m-d'));
        }

        if ($filters->createdBy !== null) {
            $query->where('created_by_user_id', $filters->createdBy);
        }

        $paginated = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filters->perPage, ['*'], 'page', $filters->page);
        $items = [];

        foreach ($paginated->items() as $sale) {
            $items[] = $this->mapItem($sale);
        }

        return new SaleListPage(
            $items,
            $paginated->currentPage(),
            $paginated->perPage(),
            $paginated->total(),
        );
    }

    private function mapItem(Sale $sale): SaleListItem
    {
        $id = $sale->getKey();
        $createdBy = $sale->getAttribute('created_by_user_id');
        $createdAt = $sale->getAttribute('created_at');
        $state = $sale->getAttribute('state');
        $paymentStatus = $sale->getAttribute('payment_status');
        $total = $sale->getAttribute('total');
        $itemCount = $sale->getAttribute('items_count');

        if (
            ! is_int($id)
            || ! is_int($createdBy)
            || ! $createdAt instanceof DateTimeInterface
            || ! is_string($state)
            || ! is_string($paymentStatus)
            || ! is_string($total)
            || ! is_int($itemCount)
        ) {
            throw new LogicException('The sale list query returned invalid resource data.');
        }

        $createdAt = DateTimeImmutable::createFromInterface($createdAt)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z');

        return new SaleListItem($id, $createdBy, $createdAt, $state, $paymentStatus, $total, $itemCount);
    }
}
