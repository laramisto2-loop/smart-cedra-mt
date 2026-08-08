<?php

use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\DistrictController;
use App\Http\Controllers\Api\GeographyTransferController;
use App\Http\Controllers\Api\GovernorateController;
use App\Http\Controllers\Api\PollingCenterController;
use App\Http\Controllers\Api\PollingStationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function (): void {
    Route::get(
        'user',
        [AuthenticatedSessionController::class, 'show']
    )->name('user.show');

    Route::get(
        'geography/transfers/{type}/template',
        [GeographyTransferController::class, 'template']
    )->name('geography.transfers.template');

    Route::get(
        'geography/transfers/{type}/export',
        [GeographyTransferController::class, 'export']
    )->name('geography.transfers.export');

    Route::apiResource('governorates', GovernorateController::class);
    Route::apiResource('districts', DistrictController::class);
    Route::apiResource('areas', AreaController::class);

    Route::apiResource(
        'polling-centers',
        PollingCenterController::class
    );

    Route::apiResource(
        'polling-stations',
        PollingStationController::class
    );

    Route::apiResource(
        'audit-logs',
        AuditLogController::class
    )->only(['index', 'show']);
});
