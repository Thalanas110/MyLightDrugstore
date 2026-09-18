<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

$routeFiles = config('modules.api_route_files', []);

if (! is_array($routeFiles) || ! array_is_list($routeFiles)) {
    throw new LogicException('The module API route files configuration must be a list.');
}

Route::prefix('v1')->group(static function () use ($routeFiles): void {
    foreach ($routeFiles as $routeFile) {
        if (! is_string($routeFile) || ! is_file($routeFile)) {
            throw new LogicException('Every configured module API route file must exist.');
        }

        require $routeFile;
    }
});
