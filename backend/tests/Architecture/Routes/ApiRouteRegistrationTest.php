<?php

declare(strict_types=1);

namespace Tests\Architecture\Routes;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

final class ApiRouteRegistrationTest extends TestCase
{
    public function test_every_module_route_file_is_declared_in_the_api_route_composition(): void
    {
        $moduleRouteFiles = array_map(
            static fn (string $routeFile): string => realpath($routeFile) ?: $routeFile,
            glob(app_path('Modules').'/*/Presentation/routes.php') ?: [],
        );
        $registeredRouteFiles = array_map(
            static fn (string $routeFile): string => realpath($routeFile) ?: $routeFile,
            config('modules.api_route_files', []),
        );

        sort($moduleRouteFiles);
        sort($registeredRouteFiles);

        $this->assertNotEmpty($moduleRouteFiles);
        $this->assertSame($moduleRouteFiles, $registeredRouteFiles);
    }

    public function test_business_routes_are_registered_only_below_the_versioned_api_prefix(): void
    {
        $routePaths = array_map(
            static fn (Route $route): string => $route->uri(),
            RouteFacade::getRoutes()->getRoutes(),
        );
        $businessRoutes = array_values(array_filter(
            $routePaths,
            static fn (string $path): bool => ! in_array($path, ['/', 'up', 'storage/{path}'], true),
        ));
        $unversionedRoutes = array_values(array_filter(
            $businessRoutes,
            static fn (string $path): bool => ! str_starts_with($path, 'api/v1/'),
        ));

        $this->assertNotEmpty($businessRoutes);
        $this->assertSame([], $unversionedRoutes);
    }
}
