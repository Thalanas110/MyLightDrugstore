<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Tests\TestCase;

final class PendingApiEndpointTest extends TestCase
{
    public function test_registered_endpoint_without_implemented_behavior_returns_a_correlated_501(): void
    {
        $requestId = 'req_pending-route';
        $response = $this->withHeader('X-Request-ID', $requestId)
            ->getJson('/api/v1/medicines');

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
