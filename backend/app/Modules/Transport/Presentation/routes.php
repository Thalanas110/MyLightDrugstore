<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PendingApiEndpointController;
use Illuminate\Support\Facades\Route;

Route::get('/transport/public-key', PendingApiEndpointController::class)
    ->name('api.v1.transport.public-key');
