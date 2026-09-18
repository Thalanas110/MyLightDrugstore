<?php

declare(strict_types=1);

use App\Modules\Identity\Presentation\AuthenticationController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/csrf', [AuthenticationController::class, 'csrf'])
    ->name('api.v1.auth.csrf');
Route::post('/auth/login', [AuthenticationController::class, 'login'])
    ->name('api.v1.auth.login');
Route::post('/auth/logout', [AuthenticationController::class, 'logout'])
    ->middleware('auth:web')
    ->name('api.v1.auth.logout');
Route::get('/auth/me', [AuthenticationController::class, 'me'])
    ->middleware('auth:web')
    ->name('api.v1.auth.me');
Route::post('/auth/change-password', [AuthenticationController::class, 'changePassword'])
    ->middleware('auth:web')
    ->name('api.v1.auth.change-password');
