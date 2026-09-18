<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PendingApiEndpointController;
use Illuminate\Support\Facades\Route;

Route::get('/sales', PendingApiEndpointController::class)
    ->name('api.v1.sales.index');
Route::post('/sales', PendingApiEndpointController::class)
    ->name('api.v1.sales.store');
Route::post('/sales/{saleId}/items', PendingApiEndpointController::class)
    ->name('api.v1.sales.items.store');
Route::delete('/sales/{saleId}/items/{saleItemId}', PendingApiEndpointController::class)
    ->name('api.v1.sales.items.destroy');
Route::get('/sales/{saleId}', PendingApiEndpointController::class)
    ->name('api.v1.sales.show');
Route::post('/sales/{saleId}/mark-paid', PendingApiEndpointController::class)
    ->name('api.v1.sales.mark-paid');
Route::post('/sales/{saleId}/cancel', PendingApiEndpointController::class)
    ->name('api.v1.sales.cancel');
