<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation;

use App\Modules\Sales\Application\MarkSalePaidAction;
use App\Modules\Sales\Application\MarkSalePaidCommand;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class MarkSalePaidController
{
    public function __invoke(string $saleId, MarkSalePaidAction $markSalePaid): JsonResponse
    {
        $parsedSaleId = filter_var($saleId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (! is_int($parsedSaleId)) {
            throw new NotFoundHttpException;
        }

        $sale = $markSalePaid->execute(new MarkSalePaidCommand($parsedSaleId));

        return response()->json([
            'data' => $sale->toArray(),
        ]);
    }
}
