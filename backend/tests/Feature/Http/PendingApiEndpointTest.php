<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Tests\Support\EncryptedTransportRequestBuilder;
use Tests\TestCase;

final class PendingApiEndpointTest extends TestCase
{
    public function test_registered_endpoint_without_implemented_behavior_returns_a_correlated_501(): void
    {
        $requestId = 'req_pending-route';
        $path = '/api/v1/medicines';
        $encrypted = (new EncryptedTransportRequestBuilder)->build('GET', $path, []);
        $response = $this->call('GET', $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_REQUEST_ID' => $requestId,
            'HTTP_X_TRANSPORT_KEY' => $encrypted['header'],
        ], json_encode($encrypted['envelope'], JSON_THROW_ON_ERROR));

        $response->assertStatus(501)
            ->assertExactJson([
                'error' => [
                    'code' => 'not_implemented',
                    'message' => 'This endpoint is registered but not implemented yet.',
                    'requestId' => $requestId,
                ],
            ])
            ->assertHeader('X-Request-ID', $requestId);
    }
}
