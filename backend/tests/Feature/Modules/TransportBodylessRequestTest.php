<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\Support\EncryptedTransportRequestBuilder;
use Tests\Support\EncryptedTransportResponseReader;
use Tests\TestCase;

final class TransportBodylessRequestTest extends TestCase
{
    public function test_get_and_delete_requests_can_send_a_wrapped_response_key_without_a_request_envelope(): void
    {
        $routeHandler = static function (Request $request): JsonResponse {
            return response()->json([
                'data' => [
                    'payload' => $request->all(),
                    'hasTransportKey' => is_string($request->attributes->get('transport_session_key')),
                ],
            ]);
        };

        Route::middleware('api')->get('/api/v1/_transport-test/bodyless-get', $routeHandler);
        Route::middleware('api')->delete('/api/v1/_transport-test/bodyless-delete', $routeHandler);

        $builder = new EncryptedTransportRequestBuilder;

        foreach ([
            'GET' => '/api/v1/_transport-test/bodyless-get',
            'DELETE' => '/api/v1/_transport-test/bodyless-delete',
        ] as $method => $path) {
            $key = $builder->buildBodyless();
            $response = $this->call($method, $path, [], [], [], ['HTTP_X_TRANSPORT_KEY' => $key['header']])
                ->assertOk();
            $descriptor = (new EncryptedTransportResponseReader)->read($response, $key['aesKey'], $method, $path);
            $this->assertSame('{"data":{"payload":[],"hasTransportKey":true}}', $descriptor['body']);
        }
    }
}
