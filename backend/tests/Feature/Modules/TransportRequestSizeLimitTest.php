<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\Support\EncryptedTransportRequestBuilder;
use Tests\Support\EncryptedTransportResponseReader;
use Tests\TestCase;

final class TransportRequestSizeLimitTest extends TestCase
{
    public function test_oversized_transport_key_header_is_rejected_before_the_route_runs(): void
    {
        $path = '/api/v1/_transport-test/oversized-key-header';
        $controllerCalls = 0;
        $this->registerCountingRoute($path, $controllerCalls);
        (new EncryptedTransportRequestBuilder)->buildBodyless();

        $response = $this->call('POST', $path, [], [], [], [
            'HTTP_X_TRANSPORT_KEY' => str_repeat('x', 4_097),
        ]);

        $response->assertBadRequest()
            ->assertJsonPath('error.code', 'transport_error')
            ->assertJsonPath('error.message', 'The encrypted request could not be processed.');
        $this->assertSame(0, $controllerCalls);
    }

    public function test_oversized_json_envelope_is_rejected_before_the_route_runs(): void
    {
        $path = '/api/v1/_transport-test/oversized-envelope';
        $controllerCalls = 0;
        $this->registerCountingRoute($path, $controllerCalls);
        $request = (new EncryptedTransportRequestBuilder)->build('POST', $path, ['probe' => true]);
        $body = json_encode($request['envelope'], JSON_THROW_ON_ERROR);
        $body .= str_repeat(' ', 1_400_001 - strlen($body));

        $response = $this->call('POST', $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TRANSPORT_KEY' => $request['header'],
        ], $body);

        $this->assertTransportError($response, $request['aesKey'], $path);
        $this->assertSame(0, $controllerCalls);
    }

    public function test_oversized_decrypted_descriptor_is_rejected_before_the_route_runs(): void
    {
        $path = '/api/v1/_transport-test/oversized-descriptor';
        $controllerCalls = 0;
        $this->registerCountingRoute($path, $controllerCalls);
        $request = (new EncryptedTransportRequestBuilder)->build('POST', $path, [
            'payload' => str_repeat('x', 524_289),
        ]);

        $response = $this->call('POST', $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TRANSPORT_KEY' => $request['header'],
        ], json_encode($request['envelope'], JSON_THROW_ON_ERROR));

        $this->assertTransportError($response, $request['aesKey'], $path);
        $this->assertSame(0, $controllerCalls);
    }

    private function registerCountingRoute(string $path, int &$controllerCalls): void
    {
        Route::middleware('api')->post($path, static function (Request $request) use (&$controllerCalls): JsonResponse {
            $controllerCalls++;

            return response()->json(['data' => $request->all()]);
        });
    }

    private function assertTransportError(TestResponse $response, string $aesKey, string $path): void
    {
        $response->assertBadRequest();
        $descriptor = (new EncryptedTransportResponseReader)->read($response, $aesKey, 'POST', $path);
        $logicalError = json_decode($descriptor['body'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('transport_error', $logicalError['error']['code'] ?? null);
    }
}
