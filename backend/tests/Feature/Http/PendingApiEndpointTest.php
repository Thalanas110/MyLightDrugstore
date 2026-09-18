<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Tests\Support\EncryptedTransportRequestBuilder;
use Tests\Support\EncryptedTransportResponseReader;
use Tests\TestCase;

final class PendingApiEndpointTest extends TestCase
{
    public function test_registered_endpoint_without_implemented_behavior_returns_a_correlated_501(): void
    {
        $requestId = 'req_pending-route';
        $path = '/api/v1/medicines';
        $encrypted = (new EncryptedTransportRequestBuilder)->buildBodyless();
        $response = $this->call('GET', $path, [], [], [], [
            'HTTP_X_REQUEST_ID' => $requestId,
            'HTTP_X_TRANSPORT_KEY' => $encrypted['header'],
        ]);

        $response->assertStatus(501)->assertHeader('X-Request-ID', $requestId);
        $descriptor = (new EncryptedTransportResponseReader)->read($response, $encrypted['aesKey'], 'GET', $path);
        $errorResponse = json_decode($descriptor['body'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([
            'error' => [
                'code' => 'not_implemented',
                'message' => 'This endpoint is registered but not implemented yet.',
                'requestId' => $requestId,
            ],
        ], $errorResponse);
    }
}
