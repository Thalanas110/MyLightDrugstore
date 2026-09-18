<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure\Persistence;

use App\Modules\Sales\Application\MarkSalePaidCommand;
use App\Modules\Sales\Application\SaleDetails;
use App\Modules\Sales\Application\SaleDetailsQuery;
use App\Modules\Sales\Application\SalePaymentMarker;
use App\Modules\Sales\Domain\SaleNotEditableException;
use Illuminate\Support\Facades\DB;
use LogicException;

final class EloquentSalePaymentMarker implements SalePaymentMarker
{
    public function __construct(private readonly SaleDetailsQuery $saleDetailsQuery) {}

    public function markPaid(MarkSalePaidCommand $command): SaleDetails
    {
        return DB::transaction(function () use ($command): SaleDetails {
            $sale = Sale::query()
                ->whereKey($command->saleId)
                ->lockForUpdate()
                ->firstOrFail();
            $state = $sale->getAttribute('state');
            $paymentStatus = $sale->getAttribute('payment_status');

            if (! is_string($state) || ! is_string($paymentStatus)) {
                throw new LogicException('The sale persistence model contains invalid state data.');
            }

            if ($state === 'completed' && $paymentStatus === 'paid') {
                return $this->findSaleDetails($command->saleId);
            }

            if ($state !== 'open' || $paymentStatus !== 'unpaid') {
                throw new SaleNotEditableException;
            }

            $sale->setAttribute('state', 'completed');
            $sale->setAttribute('payment_status', 'paid');
            $sale->save();

            return $this->findSaleDetails($command->saleId);
        });
    }

    private function findSaleDetails(int $saleId): SaleDetails
    {
        $sale = $this->saleDetailsQuery->find($saleId);

        if ($sale === null) {
            throw new LogicException('The updated sale could not be reloaded.');
        }

        return $sale;
    }
}
