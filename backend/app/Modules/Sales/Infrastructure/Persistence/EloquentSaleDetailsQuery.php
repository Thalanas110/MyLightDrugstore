<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure\Persistence;

use App\Modules\Sales\Application\SaleDetails;
use App\Modules\Sales\Application\SaleDetailsQuery;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use LogicException;

final class EloquentSaleDetailsQuery implements SaleDetailsQuery
{
    public function find(int $saleId): ?SaleDetails
    {
        $sale = Sale::query()->find($saleId);

        if ($sale === null) {
            return null;
        }

        $id = $sale->getKey();
        $createdBy = $sale->getAttribute('created_by_user_id');
        $createdAt = $sale->getAttribute('created_at');
        $state = $sale->getAttribute('state');
        $paymentStatus = $sale->getAttribute('payment_status');
        $total = $sale->getAttribute('total');

        if (
            ! is_int($id)
            || ! is_int($createdBy)
            || ! $createdAt instanceof DateTimeInterface
            || ! is_string($state)
            || ! is_string($paymentStatus)
            || ! is_string($total)
        ) {
            throw new LogicException('The sale persistence model contains invalid detail data.');
        }

        $createdAt = DateTimeImmutable::createFromInterface($createdAt)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z');
        $saleItems = SaleItem::query()->where('sale_id', $id)->orderBy('id')->get();
        $items = [];

        foreach ($saleItems as $saleItem) {
            $saleItemId = $saleItem->getKey();
            $medicineId = $saleItem->getAttribute('medicine_id');
            $quantity = $saleItem->getAttribute('quantity');
            $unitPrice = $saleItem->getAttribute('unit_price');
            $lineTotal = $saleItem->getAttribute('line_total');
            $itemState = $saleItem->getAttribute('state');

            if (
                ! is_int($saleItemId)
                || ! is_int($medicineId)
                || ! is_int($quantity)
                || ! is_string($unitPrice)
                || ! is_string($lineTotal)
                || ! is_string($itemState)
            ) {
                throw new LogicException('The sale item persistence model contains invalid detail data.');
            }

            $items[] = [
                'id' => $saleItemId,
                'medicineId' => $medicineId,
                'quantity' => $quantity,
                'unitPrice' => $unitPrice,
                'lineTotal' => $lineTotal,
                'state' => $itemState,
            ];
        }

        return new SaleDetails($id, $createdBy, $createdAt, $state, $paymentStatus, $total, $items);
    }
}
