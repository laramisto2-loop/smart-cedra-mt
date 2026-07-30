<?php

use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\DistrictController;
use App\Http\Controllers\Api\GovernorateController;
use Illuminate\Support\Facades\Route;

Route::middleware('tenant')->group(function (): void {
    Route::apiResource('governorates', GovernorateController::class);
    Route::apiResource('districts', DistrictController::class);
    Route::apiResource('areas', AreaController::class);
});
