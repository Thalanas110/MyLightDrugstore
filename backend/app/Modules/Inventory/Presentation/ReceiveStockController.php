<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation;

use App\Modules\Identity\Application\GetAuthenticatedUserAction;
use App\Modules\Inventory\Application\ReceiveStockAction;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class ReceiveStockController
{
    public function __invoke(
        ReceiveStockRequest $request,
        GetAuthenticatedUserAction $getAuthenticatedUser,
        ReceiveStockAction $receiveStock,
    ): JsonResponse {
        $user = $getAuthenticatedUser->execute();

        if ($user === null) {
            throw new AuthenticationException;
        }

        $result = $receiveStock->execute($request->toCommand($user->id));

        return response()->json([
            'data' => [
                'receiptId' => $result->receiptId,
                'receivedAt' => $result->receivedAt->format('Y-m-d\TH:i:s\Z'),
                'itemCount' => $result->itemCount,
                'totalQuantity' => $result->totalQuantity,
            ],
        ], Response::HTTP_CREATED);
    }
}
