<?php

declare(strict_types=1);

use App\Modules\Operations\Presentation\ServerTimeController;
use Illuminate\Support\Facades\Route;

Route::get('/system/time', ServerTimeController::class)
    ->name('api.v1.system.time');
