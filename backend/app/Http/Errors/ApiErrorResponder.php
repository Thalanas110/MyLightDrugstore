<?php

declare(strict_types=1);

namespace App\Http\Errors;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class ApiErrorResponder
{
    public function respond(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/v1/*')) {
            return null;
        }

        if ($exception instanceof ValidationException) {
            return $this->error(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'validation_failed',
                'The request is invalid.',
                $exception->errors(),
            );
        }

        if (
            $exception instanceof AuthenticationException
            || $this->hasStatus($exception, Response::HTTP_UNAUTHORIZED)
        ) {
            return $this->error(
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
                Response::HTTP_NOT_FOUND,
                'not_found',
                'The requested resource was not found.',
            );
        }

        if ($this->hasStatus($exception, Response::HTTP_CONFLICT)) {
            return $this->error(
                Response::HTTP_CONFLICT,
                'conflict',
                'The request conflicts with the current resource state.',
            );
        }

        return $this->error(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'internal_error',
            'An unexpected error occurred.',
        );
    }

    /**
     * @param  array<string, array<int, string>>|null  $details
     */
    private function error(int $status, string $code, string $message, ?array $details = null): JsonResponse
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($details !== null) {
            $error['details'] = $details;
        }

        return response()->json(['error' => $error], $status);
    }

    private function hasStatus(Throwable $exception, int $status): bool
    {
        return $exception instanceof HttpExceptionInterface && $exception->getStatusCode() === $status;
    }
}
