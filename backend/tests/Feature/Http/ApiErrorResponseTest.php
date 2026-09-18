<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;
use Throwable;

final class ApiErrorResponseTest extends TestCase
{
    public function test_validation_errors_include_stable_code_message_and_field_details(): void
    {
        $exception = ValidationException::withMessages([
            'items.0.quantity' => ['Must be greater than zero.'],
        ]);

        $response = $this->requestFor($exception);

        $response->assertUnprocessable()->assertExactJson([
            'error' => [
                'code' => 'validation_failed',
                'message' => 'The request is invalid.',
                'details' => [
                    'items.0.quantity' => ['Must be greater than zero.'],
                ],
            ],
        ]);
    }

    public function test_authentication_errors_use_the_documented_unauthorized_status(): void
    {
        $response = $this->requestFor(new AuthenticationException);

        $response->assertUnauthorized()->assertExactJson([
            'error' => [
                'code' => 'unauthenticated',
                'message' => 'Authentication is required.',
            ],
        ]);
    }

    public function test_authorization_errors_use_a_safe_forbidden_response(): void
    {
        $response = $this->requestFor(new AuthorizationException('Admin access only.'));

        $response->assertForbidden()->assertExactJson([
            'error' => [
                'code' => 'forbidden',
                'message' => 'You are not allowed to perform this action.',
            ],
        ]);
    }

    public function test_not_found_errors_do_not_expose_the_requested_record(): void
    {
        $response = $this->requestFor(new NotFoundHttpException('Patient record 4815 does not exist.'));

        $response->assertNotFound()->assertExactJson([
            'error' => [
                'code' => 'not_found',
                'message' => 'The requested resource was not found.',
            ],
        ]);
    }

    public function test_conflicts_use_a_stable_code_without_exposing_internal_details(): void
    {
        $response = $this->requestFor(new ConflictHttpException('Inventory row lock failed.'));

        $response->assertStatus(409)->assertExactJson([
            'error' => [
                'code' => 'conflict',
                'message' => 'The request conflicts with the current resource state.',
            ],
        ]);
    }

    public function test_unexpected_errors_return_a_generic_internal_error_without_exception_details(): void
    {
        $response = $this->requestFor(new \RuntimeException('SQLSTATE[23000] secret database detail'));

        $response->assertInternalServerError()->assertExactJson([
            'error' => [
                'code' => 'internal_error',
                'message' => 'An unexpected error occurred.',
            ],
        ]);
    }

    private function requestFor(Throwable $exception): TestResponse
    {
        Route::get('/api/v1/_contract-test/error', static function () use ($exception): never {
            throw $exception;
        });

        return $this->getJson('/api/v1/_contract-test/error');
    }
}
