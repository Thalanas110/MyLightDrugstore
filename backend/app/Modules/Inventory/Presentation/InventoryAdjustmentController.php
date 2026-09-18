<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation;

use App\Modules\Identity\Application\GetAuthenticatedUserAction;
use App\Modules\Inventory\Application\AdjustInventoryAction;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class InventoryAdjustmentController
{
    public function __invoke(
        InventoryAdjustmentRequest $request,
        GetAuthenticatedUserAction $getAuthenticatedUser,
        AdjustInventoryAction $adjustInventory,
    ): JsonResponse {
        $user = $getAuthenticatedUser->execute();

        if ($user === null) {
            throw new AuthenticationException;
        }

        $result = $adjustInventory->execute($request->toCommand($user->id));

        return response()->json([
            'data' => (new InventoryAdjustmentResource($result))->toArray(),
        ], Response::HTTP_CREATED);
    }
}
