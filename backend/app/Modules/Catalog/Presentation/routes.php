<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PendingApiEndpointController;
use App\Modules\Catalog\Presentation\MedicineCreateController;
use App\Modules\Catalog\Presentation\MedicineDetailController;
use App\Modules\Catalog\Presentation\MedicineListController;
use App\Modules\Catalog\Presentation\MedicineUpdateController;
use Illuminate\Support\Facades\Route;

Route::get('/medicines', MedicineListController::class)
    ->middleware('auth:web')
    ->name('api.v1.medicines.index');
Route::post('/medicines', MedicineCreateController::class)
    ->middleware('auth:web')
    ->name('api.v1.medicines.store');
Route::get('/medicines/{medicineId}', MedicineDetailController::class)
    ->middleware('auth:web')
    ->whereNumber('medicineId')
    ->name('api.v1.medicines.show');
Route::patch('/medicines/{medicineId}', MedicineUpdateController::class)
    ->middleware('auth:web')
    ->whereNumber('medicineId')
    ->name('api.v1.medicines.update');
Route::post('/medicines/{medicineId}/archive', PendingApiEndpointController::class)
    ->name('api.v1.medicines.archive');
