<?php

use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\CallAssignmentController;
use App\Http\Controllers\Api\CallAttemptController;
use App\Http\Controllers\Api\CallQueueController;
use App\Http\Controllers\Api\CallScriptController;
use App\Http\Controllers\Api\CampaignTaskController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContactInteractionController;
use App\Http\Controllers\Api\ContactTransferController;
use App\Http\Controllers\Api\DashboardSummaryController;
use App\Http\Controllers\Api\DistrictController;
use App\Http\Controllers\Api\ElectionContestController;
use App\Http\Controllers\Api\GeographyTransferController;
use App\Http\Controllers\Api\GovernorateController;
use App\Http\Controllers\Api\IncidentAttachmentController;
use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\Api\MessageTemplateController;
use App\Http\Controllers\Api\OutboundMessageController;
use App\Http\Controllers\Api\PlatformTenantController;
use App\Http\Controllers\Api\PollingCenterController;
use App\Http\Controllers\Api\PollingStationController;
use App\Http\Controllers\Api\ResultsAnalyticsController;
use App\Http\Controllers\Api\ResultsExportController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SegmentController;
use App\Http\Controllers\Api\TallySheetAttachmentController;
use App\Http\Controllers\Api\TallySheetController;
use App\Http\Controllers\Api\TallySubmissionController;
use App\Http\Controllers\Api\TenantSettingController;
use App\Http\Controllers\Api\TurnoutSnapshotController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    'platform',
])
    ->prefix('platform')
    ->name('platform.')
    ->group(function (): void {
        Route::get(
            'user',
            [AuthenticatedSessionController::class, 'show']
        )->name('user.show');

        Route::patch(
            'tenants/{tenant}/status',
            [PlatformTenantController::class, 'updateStatus']
        )->name('tenants.status.update');

        Route::apiResource(
            'tenants',
            PlatformTenantController::class
        )->except([
            'destroy',
        ]);
    });

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function (): void {
    Route::get(
        'dashboard-summary',
        DashboardSummaryController::class
    )->name('dashboard-summary.show');

    Route::get(
        'tenant-settings',
        [TenantSettingController::class, 'show']
    )->name('tenant-settings.show');

    Route::patch(
        'tenant-settings',
        [TenantSettingController::class, 'update']
    )->name('tenant-settings.update');

    Route::get(
        'results/analytics',
        ResultsAnalyticsController::class
    )->name('results.analytics');

    Route::get(
        'results/export',
        ResultsExportController::class
    )->name('results.export');

    Route::patch(
        'election-contests/{electionContest}/activate',
        [ElectionContestController::class, 'activate']
    )->name('election-contests.activate');

    Route::patch(
        'election-contests/{electionContest}/close',
        [ElectionContestController::class, 'close']
    )->name('election-contests.close');

    Route::apiResource(
        'election-contests',
        ElectionContestController::class
    )->parameters([
        'election-contests' => 'electionContest',
    ]);

    Route::patch(
        'tally-sheets/{tallySheet}/review',
        [TallySheetController::class, 'review']
    )->name('tally-sheets.review');

    Route::patch(
        'tally-sheets/{tallySheet}/approve',
        [TallySheetController::class, 'approve']
    )->name('tally-sheets.approve');

    Route::patch(
        'tally-sheets/{tallySheet}/reject',
        [TallySheetController::class, 'reject']
    )->name('tally-sheets.reject');

    Route::apiResource(
        'tally-sheets',
        TallySheetController::class
    )
        ->only(['index', 'store', 'show', 'update'])
        ->parameters([
            'tally-sheets' => 'tallySheet',
        ]);

    Route::post(
        'tally-sheets/{tallySheet}/submissions',
        [TallySubmissionController::class, 'store']
    )->name('tally-sheets.submissions.store');

    Route::patch(
        'tally-submissions/{tallySubmission}/submit',
        [TallySubmissionController::class, 'submit']
    )->name('tally-submissions.submit');

    Route::get(
        'tally-submissions/{tallySubmission}',
        [TallySubmissionController::class, 'show']
    )->name('tally-submissions.show');

    Route::match(
        ['put', 'patch'],
        'tally-submissions/{tallySubmission}',
        [TallySubmissionController::class, 'update']
    )->name('tally-submissions.update');

    Route::delete(
        'tally-submissions/{tallySubmission}',
        [TallySubmissionController::class, 'destroy']
    )->name('tally-submissions.destroy');

    Route::post(
        'tally-sheets/{tallySheet}/attachments',
        [TallySheetAttachmentController::class, 'store']
    )->name('tally-sheets.attachments.store');

    Route::get(
        'tally-sheet-attachments/{tallySheetAttachment}/download',
        [TallySheetAttachmentController::class, 'download']
    )->name('tally-sheet-attachments.download');

    Route::delete(
        'tally-sheet-attachments/{tallySheetAttachment}',
        [TallySheetAttachmentController::class, 'destroy']
    )->name('tally-sheet-attachments.destroy');

    Route::patch(
        'users/{user}/roles',
        [UserController::class, 'syncRoles']
    )->name('users.roles.sync');

    Route::apiResource(
        'users',
        UserController::class
    );

    // This static route must remain before the roles resource route.
    Route::get(
        'roles/permissions',
        [RoleController::class, 'permissions']
    )->name('roles.permissions.index');

    Route::patch(
        'roles/{role}/permissions',
        [RoleController::class, 'syncPermissions']
    )->name('roles.permissions.sync');

    Route::apiResource(
        'roles',
        RoleController::class
    );
    Route::patch(
        'call-scripts/{callScript}/activate',
        [CallScriptController::class, 'activate']
    )->name('call-scripts.activate');

    Route::apiResource(
        'call-scripts',
        CallScriptController::class
    )->parameters([
        'call-scripts' => 'callScript',
    ]);

    Route::post(
        'call-queues/{callQueue}/assign',
        [CallQueueController::class, 'assign']
    )->name('call-queues.assign');

    Route::apiResource(
        'call-queues',
        CallQueueController::class
    )->parameters([
        'call-queues' => 'callQueue',
    ]);

    Route::patch(
        'call-assignments/{callAssignment}/claim',
        [CallAssignmentController::class, 'claim']
    )->name('call-assignments.claim');

    Route::apiResource(
        'call-assignments',
        CallAssignmentController::class
    )
        ->only([
            'index',
            'show',
            'update',
        ])
        ->parameters([
            'call-assignments' => 'callAssignment',
        ]);

    Route::apiResource(
        'call-attempts',
        CallAttemptController::class
    )
        ->only([
            'index',
            'store',
            'show',
        ])
        ->parameters([
            'call-attempts' => 'callAttempt',
        ]);

    Route::patch(
        'message-templates/{messageTemplate}/approve',
        [MessageTemplateController::class, 'approve']
    )->name('message-templates.approve');

    Route::apiResource(
        'message-templates',
        MessageTemplateController::class
    )->parameters([
        'message-templates' => 'messageTemplate',
    ]);

    Route::get(
        'outbound-messages/{outboundMessage}/delivery-events',
        [OutboundMessageController::class, 'deliveryEvents']
    )->name('outbound-messages.delivery-events');

    Route::apiResource(
        'outbound-messages',
        OutboundMessageController::class
    )
        ->only([
            'index',
            'store',
            'show',
        ])
        ->parameters([
            'outbound-messages' => 'outboundMessage',
        ]);

    Route::get(
        'turnout-snapshots/series',
        [TurnoutSnapshotController::class, 'series']
    )->name('turnout-snapshots.series');

    Route::apiResource(
        'turnout-snapshots',
        TurnoutSnapshotController::class
    )
        ->only([
            'index',
            'store',
            'show',
        ])
        ->parameters([
            'turnout-snapshots' => 'turnoutSnapshot',
        ]);

    Route::get(
        'user',
        [AuthenticatedSessionController::class, 'show']
    )->name('user.show');

    Route::patch(
        'incidents/{incident}/assign',
        [IncidentController::class, 'assign']
    )->name('incidents.assign');

    Route::patch(
        'incidents/{incident}/review',
        [IncidentController::class, 'review']
    )->name('incidents.review');

    Route::post(
        'incidents/{incident}/attachments',
        [IncidentAttachmentController::class, 'store']
    )->name('incidents.attachments.store');

    Route::get(
        'incident-attachments/{incidentAttachment}/download',
        [IncidentAttachmentController::class, 'download']
    )->name('incident-attachments.download');

    Route::delete(
        'incident-attachments/{incidentAttachment}',
        [IncidentAttachmentController::class, 'destroy']
    )->name('incident-attachments.destroy');

    Route::apiResource(
        'incidents',
        IncidentController::class
    );

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

    Route::get(
        'contacts/transfers/template',
        [ContactTransferController::class, 'template']
    )->name('contacts.transfers.template');

    Route::get(
        'contacts/transfers/export',
        [ContactTransferController::class, 'export']
    )->name('contacts.transfers.export');

    Route::post(
        'contacts/{contact}/consents',
        [ContactController::class, 'recordConsent']
    )->name('contacts.consents.record');

    Route::post(
        'contacts/transfers/preview',
        [ContactTransferController::class, 'preview']
    )->name('contacts.transfers.preview');

    Route::post(
        'contacts/transfers/import',
        [ContactTransferController::class, 'import']
    )->name('contacts.transfers.import');

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

    Route::get(
        'campaign-tasks/assignees',
        [CampaignTaskController::class, 'assignees']
    )->name('campaign-tasks.assignees');

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
