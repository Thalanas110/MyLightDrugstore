<?php

declare(strict_types=1);

namespace Tests\Architecture\Routes;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

final class SalesRouteAuthorizationTest extends TestCase
{
    public function test_every_sales_endpoint_requires_an_authenticated_session(): void
    {
        $salesRoutes = array_values(array_filter(
            RouteFacade::getRoutes()->getRoutes(),
            static fn (Route $route): bool => str_starts_with($route->uri(), 'api/v1/sales'),
        ));

        $this->assertNotEmpty($salesRoutes);

        foreach ($salesRoutes as $route) {
            $this->assertContains('auth:web', $route->middleware(), $route->uri());
        }
    }
}
