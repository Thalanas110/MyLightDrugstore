<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PendingApiEndpointController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/csrf', PendingApiEndpointController::class)
    ->name('api.v1.auth.csrf');
Route::post('/auth/login', PendingApiEndpointController::class)
    ->name('api.v1.auth.login');
Route::post('/auth/logout', PendingApiEndpointController::class)
    ->name('api.v1.auth.logout');
Route::get('/auth/me', PendingApiEndpointController::class)
    ->name('api.v1.auth.me');
Route::post('/auth/change-password', PendingApiEndpointController::class)
    ->name('api.v1.auth.change-password');
