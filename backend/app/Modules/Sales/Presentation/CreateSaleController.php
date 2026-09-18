<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation;

use App\Modules\Sales\Application\CreateSaleAction;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final class CreateSaleController
{
    public function __invoke(CreateSaleRequest $request, CreateSaleAction $createSale): JsonResponse
    {
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

        $sale = $createSale->execute($request->toCommand($actorUserId));

        return response()->json([
            'data' => $sale->toArray(),
        ], Response::HTTP_CREATED);
    }
}
