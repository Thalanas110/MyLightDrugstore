<?php

declare(strict_types=1);

return [
    'entry_points' => [
        __DIR__.'/../app/Modules/Identity/index.php',
        __DIR__.'/../app/Modules/Catalog/index.php',
        __DIR__.'/../app/Modules/Inventory/index.php',
        __DIR__.'/../app/Modules/Sales/index.php',
        __DIR__.'/../app/Modules/Users/index.php',
        __DIR__.'/../app/Modules/Reporting/index.php',
        __DIR__.'/../app/Modules/Operations/index.php',
        __DIR__.'/../app/Modules/Transport/index.php',
        __DIR__.'/../app/Modules/Security/index.php',
    ],
    'api_route_files' => [
        __DIR__.'/../app/Modules/Identity/Presentation/routes.php',
        __DIR__.'/../app/Modules/Catalog/Presentation/routes.php',
        __DIR__.'/../app/Modules/Inventory/Presentation/routes.php',
        __DIR__.'/../app/Modules/Sales/Presentation/routes.php',
        __DIR__.'/../app/Modules/Users/Presentation/routes.php',
        __DIR__.'/../app/Modules/Reporting/Presentation/routes.php',
        __DIR__.'/../app/Modules/Operations/Presentation/routes.php',
        __DIR__.'/../app/Modules/Transport/Presentation/routes.php',
    ],
];
