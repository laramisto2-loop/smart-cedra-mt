<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class CallAttempt extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    public const OUTCOMES = [
        'completed',
        'no_answer',
        'busy',
        'voicemail',
        'wrong_number',
        'declined',
        'callback_requested',
        'failed',
    ];

    protected $fillable = [
        'tenant_id',
        'call_assignment_id',
        'performed_by_user_id',
        'follow_up_task_id',
        'client_uuid',
        'reference_code',
        'outcome',
        'duration_seconds',
        'notes',
        'attempted_at',
        'follow_up_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'attempted_at' => 'datetime',
            'follow_up_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CallAttempt $attempt): void {
            $tenantId = app(TenantContext::class)->id()
                ?? $attempt->tenant_id;

            if ($tenantId === null) {
                throw new LogicException(
                    'A call attempt must belong to a tenant.'
                );
            }

            if ($attempt->call_assignment_id === null) {
                throw new LogicException(
                    'A call attempt must belong to an assignment.'
                );
            }

            if ($attempt->performed_by_user_id === null) {
                throw new LogicException(
                    'A call attempt must record the agent who performed it.'
                );
            }

            if (blank($attempt->client_uuid)) {
                $attempt->client_uuid = Str::uuid()->toString();
            }

            if (blank($attempt->reference_code)) {
                $compactUuid = str_replace(
                    '-',
                    '',
                    (string) $attempt->client_uuid
                );

                $attempt->reference_code = 'CALL-'.strtoupper(
                    substr($compactUuid, 0, 12)
                );
            }

            if ($attempt->attempted_at === null) {
                $attempt->attempted_at = now();
            }

            if (
                ! in_array(
                    $attempt->outcome,
                    self::OUTCOMES,
                    true
                )
            ) {
                throw new LogicException(
                    'The call attempt outcome is invalid.'
                );
            }

            if (
                $attempt->duration_seconds !== null
                && (int) $attempt->duration_seconds < 0
            ) {
                throw new LogicException(
                    'The call duration cannot be negative.'
                );
            }

            if (
                $attempt->outcome === 'callback_requested'
                && $attempt->follow_up_at === null
            ) {
                throw new LogicException(
                    'A requested callback must include a follow-up time.'
                );
            }

            self::ensureRelationshipBelongsToTenant(
                CallAssignment::class,
                $attempt->call_assignment_id,
                (int) $tenantId,
                'assignment'
            );

            self::ensureRelationshipBelongsToTenant(
                User::class,
                $attempt->performed_by_user_id,
                (int) $tenantId,
                'agent'
            );

            self::ensureRelationshipBelongsToTenant(
                CampaignTask::class,
                $attempt->follow_up_task_id,
                (int) $tenantId,
                'follow-up task'
            );
        });

        static::updating(function (): void {
            throw new LogicException(
                'Call attempts are immutable. Record another attempt to correct the call history.'
            );
        });

        static::deleting(function (): void {
            throw new LogicException(
                'Call attempts cannot be deleted because they form part of the call history.'
            );
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function callAssignment(): BelongsTo
    {
        return $this->belongsTo(CallAssignment::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'performed_by_user_id'
        );
    }

    public function followUpTask(): BelongsTo
    {
        return $this->belongsTo(
            CampaignTask::class,
            'follow_up_task_id'
        );
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
                "The call attempt {$relationship} must belong to the same tenant."
            );
        }
    }
}
