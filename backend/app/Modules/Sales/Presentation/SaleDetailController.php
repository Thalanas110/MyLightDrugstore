<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation;

use App\Modules\Sales\Application\GetSaleDetailsAction;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class SaleDetailController
{
    public function __invoke(string $saleId, GetSaleDetailsAction $getSaleDetails): JsonResponse
    {
        $parsedSaleId = filter_var($saleId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (! is_int($parsedSaleId)) {
            throw new NotFoundHttpException;
        }

        $sale = $getSaleDetails->execute($parsedSaleId);

        if ($sale === null) {
            throw new NotFoundHttpException;
        }

        return response()->json([
            'data' => $sale->toArray(),
        ]);
    }
}
