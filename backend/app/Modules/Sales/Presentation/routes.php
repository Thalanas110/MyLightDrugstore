<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PendingApiEndpointController;
use App\Modules\Sales\Presentation\CreateSaleController;
use App\Modules\Sales\Presentation\SaleDetailController;
use Illuminate\Support\Facades\Route;

Route::get('/sales', PendingApiEndpointController::class)
    ->middleware('auth:web')
    ->name('api.v1.sales.index');
Route::post('/sales', CreateSaleController::class)
    ->middleware('auth:web')
    ->name('api.v1.sales.store');
Route::post('/sales/{saleId}/items', PendingApiEndpointController::class)
    ->middleware('auth:web')
    ->name('api.v1.sales.items.store');
Route::delete('/sales/{saleId}/items/{saleItemId}', PendingApiEndpointController::class)
    ->middleware('auth:web')
    ->name('api.v1.sales.items.destroy');
Route::get('/sales/{saleId}', SaleDetailController::class)
    ->middleware('auth:web')
    ->name('api.v1.sales.show');
Route::post('/sales/{saleId}/mark-paid', PendingApiEndpointController::class)
    ->middleware('auth:web')
    ->name('api.v1.sales.mark-paid');
Route::post('/sales/{saleId}/cancel', PendingApiEndpointController::class)
    ->middleware('auth:web')
    ->name('api.v1.sales.cancel');
