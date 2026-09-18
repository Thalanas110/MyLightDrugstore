<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PendingApiEndpointController;
use Illuminate\Support\Facades\Route;

Route::get('/users', PendingApiEndpointController::class)
    ->name('api.v1.users.index');
Route::post('/users', PendingApiEndpointController::class)
    ->name('api.v1.users.store');
Route::get('/users/{userId}', PendingApiEndpointController::class)
    ->name('api.v1.users.show');
Route::patch('/users/{userId}', PendingApiEndpointController::class)
    ->name('api.v1.users.update');
