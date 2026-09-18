<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Middleware\AttachRequestId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class PendingApiEndpointController
{
    public function __invoke(Request $request): JsonResponse
    {
        $requestId = $request->attributes->get(AttachRequestId::ATTRIBUTE);
        $requestId = is_string($requestId) ? $requestId : 'req_'.Str::ulid();

        return response()->json([
            'error' => [
                'code' => 'not_implemented',
                'message' => 'This endpoint is registered but not implemented yet.',
                'requestId' => $requestId,
            ],
        ], Response::HTTP_NOT_IMPLEMENTED);
    }
}
