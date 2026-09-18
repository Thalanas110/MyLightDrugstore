<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PendingApiEndpointController;
use Illuminate\Support\Facades\Route;

Route::get('/reports/inventory', PendingApiEndpointController::class)
    ->name('api.v1.reports.inventory');
Route::get('/reports/sales', PendingApiEndpointController::class)
    ->name('api.v1.reports.sales');
