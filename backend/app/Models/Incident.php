<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class Incident extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    public const CATEGORIES = [
        'general',
        'access',
        'safety',
        'medical',
        'equipment',
        'logistics',
        'conduct',
        'other',
    ];

    public const SEVERITIES = [
        'low',
        'medium',
        'high',
        'critical',
    ];

    public const STATUSES = [
        'submitted',
        'in_review',
        'resolved',
        'dismissed',
    ];

    protected $fillable = [
        'tenant_id',
        'reported_by_user_id',
        'assigned_to_user_id',
        'reviewed_by_user_id',
        'campaign_task_id',
        'area_id',
        'polling_center_id',
        'polling_station_id',
        'client_uuid',
        'reference_code',
        'title',
        'description',
        'category',
        'severity',
        'status',
        'location_notes',
        'occurred_at',
        'reported_at',
        'reviewed_at',
        'resolved_at',
        'resolution_notes',
        'client_updated_at',
        'sync_version',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'reported_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'resolved_at' => 'datetime',
            'client_updated_at' => 'datetime',
            'sync_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Incident $incident): void {
            $tenantId = app(TenantContext::class)->id()
                ?? $incident->tenant_id;

            if ($tenantId === null) {
                throw new LogicException(
                    'An incident must belong to a tenant.'
                );
            }

            if (blank($incident->client_uuid)) {
                $incident->client_uuid = Str::uuid()->toString();
            }

            if (blank($incident->reference_code)) {
                $compactUuid = str_replace(
                    '-',
                    '',
                    (string) $incident->client_uuid
                );

                $incident->reference_code = 'INC-'.strtoupper(
                    substr($compactUuid, 0, 12)
                );
            }

            self::ensureRelationshipBelongsToTenant(
                User::class,
                $incident->reported_by_user_id,
                (int) $tenantId,
                'reporter'
            );

            self::ensureRelationshipBelongsToTenant(
                User::class,
                $incident->assigned_to_user_id,
                (int) $tenantId,
                'assignee'
            );

            self::ensureRelationshipBelongsToTenant(
                User::class,
                $incident->reviewed_by_user_id,
                (int) $tenantId,
                'reviewer'
            );

            self::ensureRelationshipBelongsToTenant(
                CampaignTask::class,
                $incident->campaign_task_id,
                (int) $tenantId,
                'campaign task'
            );

            self::ensureRelationshipBelongsToTenant(
                Area::class,
                $incident->area_id,
                (int) $tenantId,
                'area'
            );

            self::ensureRelationshipBelongsToTenant(
                PollingCenter::class,
                $incident->polling_center_id,
                (int) $tenantId,
                'polling center'
            );

            self::ensureRelationshipBelongsToTenant(
                PollingStation::class,
                $incident->polling_station_id,
                (int) $tenantId,
                'polling station'
            );

            self::ensureGeographyIsConsistent($incident);
            self::applyWorkflowTimestamps($incident);

            if (
                $incident->exists
                && ! $incident->isDirty('sync_version')
            ) {
                $incident->sync_version = max(
                    1,
                    (int) $incident->getOriginal('sync_version') + 1
                );
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reported_by_user_id'
        );
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'assigned_to_user_id'
        );
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by_user_id'
        );
    }

    public function campaignTask(): BelongsTo
    {
        return $this->belongsTo(CampaignTask::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function pollingCenter(): BelongsTo
    {
        return $this->belongsTo(PollingCenter::class);
    }

    public function pollingStation(): BelongsTo
    {
        return $this->belongsTo(PollingStation::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(IncidentAttachment::class);
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private static function ensureRelationshipBelongsToTenant(
        string $modelClass,
        ?int $relatedId,
        int $tenantId,
        string $relationship
    ): void {
        if ($relatedId === null) {
            return;
        }

        $relatedTenantId = $modelClass::withoutGlobalScopes()
            ->whereKey($relatedId)
            ->value('tenant_id');

        if ((int) $relatedTenantId !== $tenantId) {
            throw new LogicException(
                "The incident {$relationship} must belong to the same tenant."
            );
        }
    }

    private static function ensureGeographyIsConsistent(
        Incident $incident
    ): void {
        $pollingCenter = $incident->polling_center_id === null
            ? null
            : PollingCenter::withoutGlobalScopes()
                ->find($incident->polling_center_id);

        $pollingStation = $incident->polling_station_id === null
            ? null
            : PollingStation::withoutGlobalScopes()
                ->find($incident->polling_station_id);

        if (
            $pollingCenter !== null
            && $incident->area_id !== null
            && (int) $pollingCenter->area_id !== (int) $incident->area_id
        ) {
            throw new LogicException(
                'The incident polling center must belong to the selected area.'
            );
        }

        if (
            $pollingStation !== null
            && $pollingCenter !== null
            && (int) $pollingStation->polling_center_id
                !== (int) $pollingCenter->id
        ) {
            throw new LogicException(
                'The incident polling station must belong to the selected polling center.'
            );
        }

        if (
            $pollingStation !== null
            && $pollingCenter === null
            && $incident->area_id !== null
        ) {
            $stationAreaId = PollingCenter::withoutGlobalScopes()
                ->whereKey($pollingStation->polling_center_id)
                ->value('area_id');

            if ((int) $stationAreaId !== (int) $incident->area_id) {
                throw new LogicException(
                    'The incident polling station must belong to the selected area.'
                );
            }
        }
    }

    private static function applyWorkflowTimestamps(
        Incident $incident
    ): void {
        if (
            in_array(
                $incident->status,
                ['in_review', 'resolved', 'dismissed'],
                true
            )
            && $incident->reviewed_at === null
        ) {
            $incident->reviewed_at = now();
        }

        if (
            $incident->status === 'resolved'
            && $incident->resolved_at === null
        ) {
            $incident->resolved_at = now();
        }

        if ($incident->status !== 'resolved') {
            $incident->resolved_at = null;
        }

        if ($incident->status === 'submitted') {
            $incident->reviewed_at = null;
            $incident->reviewed_by_user_id = null;
            $incident->resolved_at = null;
            $incident->resolution_notes = null;
        }
    }
}
