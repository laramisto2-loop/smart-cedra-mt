<?php

use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\CampaignTaskController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContactInteractionController;
use App\Http\Controllers\Api\DistrictController;
use App\Http\Controllers\Api\GeographyTransferController;
use App\Http\Controllers\Api\GovernorateController;
use App\Http\Controllers\Api\PollingCenterController;
use App\Http\Controllers\Api\PollingStationController;
use App\Http\Controllers\Api\SegmentController;
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

    Route::post(
        'geography/transfers/{type}/preview',
        [GeographyTransferController::class, 'preview']
    )->name('geography.transfers.preview');

    Route::post(
        'geography/transfers/{type}/import',
        [GeographyTransferController::class, 'import']
    )->name('geography.transfers.import');

    Route::post(
        'contacts/{contact}/consents',
        [ContactController::class, 'recordConsent']
    )->name('contacts.consents.record');

    Route::get(
        'contacts/{contact}/interactions',
        [ContactInteractionController::class, 'index']
    )->name('contacts.interactions.index');

    Route::post(
        'contacts/{contact}/interactions',
        [ContactInteractionController::class, 'store']
    )->name('contacts.interactions.store');

    Route::get(
        'contact-interactions/{contactInteraction}',
        [ContactInteractionController::class, 'show']
    )->name('contact-interactions.show');

    Route::match(
        ['put', 'patch'],
        'contact-interactions/{contactInteraction}',
        [ContactInteractionController::class, 'update']
    )->name('contact-interactions.update');

    Route::delete(
        'contact-interactions/{contactInteraction}',
        [ContactInteractionController::class, 'destroy']
    )->name('contact-interactions.destroy');

    Route::get(
        'segments/{segment}/members',
        [SegmentController::class, 'members']
    )->name('segments.members.index');

    Route::put(
        'segments/{segment}/members',
        [SegmentController::class, 'syncMembers']
    )->name('segments.members.sync');

    Route::patch(
        'campaign-tasks/{campaignTask}/assign',
        [CampaignTaskController::class, 'assign']
    )->name('campaign-tasks.assign');

    Route::patch(
        'campaign-tasks/{campaignTask}/complete',
        [CampaignTaskController::class, 'complete']
    )->name('campaign-tasks.complete');

    Route::apiResource(
        'campaign-tasks',
        CampaignTaskController::class
    )->parameters([
        'campaign-tasks' => 'campaignTask',
    ]);

    Route::apiResource(
        'segments',
        SegmentController::class
    );

    Route::apiResource(
        'contacts',
        ContactController::class
    );

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
