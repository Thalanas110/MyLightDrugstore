<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PendingApiEndpointController;
use App\Modules\Inventory\Presentation\InventorySummaryController;
use App\Modules\Inventory\Presentation\ReceiveStockController;
use Illuminate\Support\Facades\Route;

Route::get('/inventory', InventorySummaryController::class)
    ->middleware('auth:web')
    ->name('api.v1.inventory.index');
Route::get('/inventory/lots', PendingApiEndpointController::class)
    ->middleware('auth:web')
    ->name('api.v1.inventory.lots.index');
Route::post('/inventory/receipts', ReceiveStockController::class)
    ->middleware('auth:web')
    ->name('api.v1.inventory.receipts.store');
Route::post('/inventory/adjustments', PendingApiEndpointController::class)
    ->middleware('auth:web')
    ->name('api.v1.inventory.adjustments.store');
Route::get('/inventory/movements', PendingApiEndpointController::class)
    ->middleware('auth:web')
    ->name('api.v1.inventory.movements.index');
