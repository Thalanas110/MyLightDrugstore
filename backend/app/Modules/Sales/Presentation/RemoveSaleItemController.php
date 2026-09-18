<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation;

use App\Modules\Sales\Application\RemoveSaleItemAction;
use App\Modules\Sales\Application\RemoveSaleItemCommand;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RemoveSaleItemController
{
    public function __invoke(
        string $saleId,
        string $saleItemId,
        Request $request,
        RemoveSaleItemAction $removeSaleItem,
    ): JsonResponse {
        $parsedSaleId = filter_var($saleId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $parsedSaleItemId = filter_var($saleItemId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (! is_int($parsedSaleId) || ! is_int($parsedSaleItemId)) {
            throw new NotFoundHttpException;
        }

        $user = $request->user('web');

        if ($user === null) {
            throw new AuthenticationException;
        }

        $actorUserId = $user->getAuthIdentifier();

        if (is_string($actorUserId) && ctype_digit($actorUserId)) {
            $actorUserId = (int) $actorUserId;
        }

        if (! is_int($actorUserId)) {
            throw new LogicException('The authenticated user identifier is invalid.');
        }

        $sale = $removeSaleItem->execute(new RemoveSaleItemCommand(
            $actorUserId,
            $parsedSaleId,
            $parsedSaleItemId,
        ));

        return response()->json([
            'data' => $sale->toArray(),
        ]);
    }
}
