<?php

declare(strict_types=1);

use App\Modules\Transport\Presentation\TransportPublicKeyController;
use Illuminate\Support\Facades\Route;

Route::get('/transport/public-key', TransportPublicKeyController::class)
    ->name('api.v1.transport.public-key');
