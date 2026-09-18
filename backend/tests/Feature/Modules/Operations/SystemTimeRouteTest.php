<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Operations;

use Tests\Support\EncryptedTransportRequestBuilder;
use Tests\TestCase;

final class SystemTimeRouteTest extends TestCase
{
    public function test_versioned_system_time_route_returns_the_utc_api_resource(): void
    {
        $path = '/api/v1/system/time';
        $encrypted = (new EncryptedTransportRequestBuilder)->buildBodyless();
        $response = $this->call('GET', $path, [], [], [], [
            'HTTP_X_TRANSPORT_KEY' => $encrypted['header'],
        ]);

        $response->assertOk()->assertJsonStructure([
            'data' => ['now'],
        ]);

        $now = $response->json('data.now');

        $this->assertIsString($now);
        $this->assertMatchesRegularExpression('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/', $now);
    }
}
