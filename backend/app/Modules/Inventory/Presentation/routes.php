<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PendingApiEndpointController;
use Illuminate\Support\Facades\Route;

Route::get('/inventory', PendingApiEndpointController::class)
    ->name('api.v1.inventory.index');
Route::get('/inventory/lots', PendingApiEndpointController::class)
    ->name('api.v1.inventory.lots.index');
Route::post('/inventory/receipts', PendingApiEndpointController::class)
    ->name('api.v1.inventory.receipts.store');
Route::post('/inventory/adjustments', PendingApiEndpointController::class)
    ->name('api.v1.inventory.adjustments.store');
Route::get('/inventory/movements', PendingApiEndpointController::class)
    ->name('api.v1.inventory.movements.index');
