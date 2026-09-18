<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Illuminate\Log\Logger as LaravelLogger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use Tests\TestCase;
use Throwable;

final class RequestIdTest extends TestCase
{
    public function test_safe_client_request_id_is_returned_in_the_error_body_and_response_header(): void
    {
        $requestId = 'req_client-42';
        $this->registerFailingRoute(new \RuntimeException('Request failed.'));

        $response = $this->withHeader('X-Request-ID', $requestId)
            ->getJson('/api/v1/_contract-test/request-id');

        $response->assertInternalServerError()
            ->assertJsonPath('error.requestId', $requestId)
            ->assertHeader('X-Request-ID', $requestId);
    }

    public function test_missing_or_unsafe_client_request_ids_are_replaced_with_generated_ids(): void
    {
        $this->registerFailingRoute(new \RuntimeException('Request failed.'));

        $missingIdResponse = $this->getJson('/api/v1/_contract-test/request-id');
        $unsafeIdResponse = $this->withHeader('X-Request-ID', 'client id with spaces')
            ->getJson('/api/v1/_contract-test/request-id');

        $missingId = $missingIdResponse->json('error.requestId');
        $unsafeId = $unsafeIdResponse->json('error.requestId');

        $this->assertIsString($missingId);
        $this->assertIsString($unsafeId);
        $this->assertMatchesRegularExpression('/\Areq_[0-9A-HJKMNP-TV-Z]{26}\z/', $missingId);
        $this->assertMatchesRegularExpression('/\Areq_[0-9A-HJKMNP-TV-Z]{26}\z/', $unsafeId);
        $this->assertNotSame('client id with spaces', $unsafeId);
        $this->assertSame($unsafeId, $unsafeIdResponse->headers->get('X-Request-ID'));
    }

    public function test_request_id_is_added_to_request_and_exception_log_context(): void
    {
        $requestId = 'req_log-correlation';
        $logger = Log::driver();
        $this->assertInstanceOf(LaravelLogger::class, $logger);
        $handler = new TestHandler;
        $logger->getLogger()->pushHandler($handler);

        Route::get('/api/v1/_contract-test/request-id-log', static function (): never {
            Log::info('request-id-context-probe');

            throw new \RuntimeException('Correlated failure.');
        });

        $response = $this->withHeader('X-Request-ID', $requestId)
            ->getJson('/api/v1/_contract-test/request-id-log');

        $response->assertInternalServerError()
            ->assertJsonPath('error.requestId', $requestId);

        $normalLogRecords = array_values(array_filter(
            $handler->getRecords(),
            static fn (LogRecord $record): bool => $record->message === 'request-id-context-probe',
        ));
        $exceptionLogRecords = array_values(array_filter(
            $handler->getRecords(),
            static fn (LogRecord $record): bool => isset($record->context['exception']),
        ));

        $this->assertCount(1, $normalLogRecords);
        $this->assertSame($requestId, $normalLogRecords[0]->extra['request_id']);
        $this->assertNotEmpty($exceptionLogRecords);
        $this->assertSame($requestId, $exceptionLogRecords[0]->context['request_id']);
    }

    private function registerFailingRoute(Throwable $exception): void
    {
        Route::get('/api/v1/_contract-test/request-id', static function () use ($exception): never {
            throw $exception;
        });
    }
}
