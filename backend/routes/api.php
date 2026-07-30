<?php

use App\Http\Controllers\Api\GovernorateController;
use Illuminate\Support\Facades\Route;

Route::middleware('tenant')->group(function (): void {
    Route::apiResource('governorates', GovernorateController::class);
});
