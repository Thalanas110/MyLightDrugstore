<?php

declare(strict_types=1);

namespace App\Http\Errors;

use App\Http\Middleware\AttachRequestId;
use App\Modules\Inventory\Domain\InsufficientStockException;
use App\Modules\Inventory\Domain\SaleItemStockHistoryMissingException;
use App\Modules\Sales\Domain\IdempotencyKeyReusedException;
use App\Modules\Sales\Domain\SaleNotEditableException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class ApiErrorResponder
{
    private const int CSRF_TOKEN_MISMATCH_STATUS = 419;

    public function respond(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/v1/*')) {
            return null;
        }

        if ($exception instanceof ValidationException) {
            return $this->error(
                $request,
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'validation_failed',
                'The request is invalid.',
                $exception->errors(),
            );
        }

        if (
            $exception instanceof TokenMismatchException
            || $this->hasStatus($exception, self::CSRF_TOKEN_MISMATCH_STATUS)
        ) {
            return $this->error(
                $request,
                self::CSRF_TOKEN_MISMATCH_STATUS,
                'csrf_token_mismatch',
                'The CSRF token is invalid or expired.',
            );
        }

        if (
            $exception instanceof AuthenticationException
            || $this->hasStatus($exception, Response::HTTP_UNAUTHORIZED)
        ) {
            return $this->error(
                $request,
                Response::HTTP_UNAUTHORIZED,
                'unauthenticated',
                'Authentication is required.',
            );
        }

        if (
            $exception instanceof AuthorizationException
            || $this->hasStatus($exception, Response::HTTP_FORBIDDEN)
        ) {
            return $this->error(
                $request,
                Response::HTTP_FORBIDDEN,
                'forbidden',
                'You are not allowed to perform this action.',
            );
        }

        if (
            $exception instanceof ModelNotFoundException
            || $exception instanceof NotFoundHttpException
            || $this->hasStatus($exception, Response::HTTP_NOT_FOUND)
        ) {
            return $this->error(
                $request,
                Response::HTTP_NOT_FOUND,
                'not_found',
                'The requested resource was not found.',
            );
        }

        if ($exception instanceof InsufficientStockException) {
            return $this->error(
                $request,
                Response::HTTP_CONFLICT,
                'insufficient_stock',
                'There is not enough eligible stock for this sale.',
            );
        }

        if ($exception instanceof SaleItemStockHistoryMissingException) {
            return $this->error(
                $request,
                Response::HTTP_CONFLICT,
                'conflict',
                'The request conflicts with the current resource state.',
            );
        }

        if ($exception instanceof IdempotencyKeyReusedException) {
            return $this->error(
                $request,
                Response::HTTP_CONFLICT,
                'idempotency_key_reused',
                'The idempotency key was already used for a different request.',
            );
        }

        if ($exception instanceof SaleNotEditableException) {
            return $this->error(
                $request,
                Response::HTTP_CONFLICT,
                'conflict',
                'The request conflicts with the current resource state.',
            );
        }

        if ($this->hasStatus($exception, Response::HTTP_CONFLICT)) {
            return $this->error(
                $request,
                Response::HTTP_CONFLICT,
                'conflict',
                'The request conflicts with the current resource state.',
            );
        }

        return $this->error(
            $request,
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'internal_error',
            'An unexpected error occurred.',
        );
    }

    /**
     * @param  array<string, array<int, string>>|null  $details
     */
    private function error(Request $request, int $status, string $code, string $message, ?array $details = null): JsonResponse
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        $requestId = $request->attributes->get(AttachRequestId::ATTRIBUTE);

        if ($details !== null) {
            $error['details'] = $details;
        }

        if (is_string($requestId)) {
            $error['requestId'] = $requestId;
        }

        $response = response()->json(['error' => $error], $status);

        if (is_string($requestId)) {
            $response->headers->set(AttachRequestId::HEADER, $requestId);
        }

        return $response;
    }

    private function hasStatus(Throwable $exception, int $status): bool
    {
        return $exception instanceof HttpExceptionInterface && $exception->getStatusCode() === $status;
    }
}
