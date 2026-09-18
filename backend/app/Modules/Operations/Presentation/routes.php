<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PendingApiEndpointController;
use App\Modules\Operations\Presentation\ServerTimeController;
use Illuminate\Support\Facades\Route;

Route::post('/backups', PendingApiEndpointController::class)
    ->name('api.v1.backups.store');
Route::get('/backups/{backupId}', PendingApiEndpointController::class)
    ->name('api.v1.backups.show');
Route::get('/backups/{backupId}/download', PendingApiEndpointController::class)
    ->name('api.v1.backups.download');
Route::get('/system/time', ServerTimeController::class)
    ->name('api.v1.system.time');
