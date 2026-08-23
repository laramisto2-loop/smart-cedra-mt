<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class CallAssignment extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    public const PRIORITIES = [
        'low',
        'normal',
        'high',
        'urgent',
    ];

    public const STATUSES = [
        'pending',
        'in_progress',
        'completed',
        'skipped',
        'cancelled',
    ];

    protected $fillable = [
        'tenant_id',
        'call_queue_id',
        'contact_id',
        'assigned_to_user_id',
        'assigned_by_user_id',
        'status',
        'priority',
        'scheduled_for',
        'claimed_at',
        'last_attempted_at',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'claimed_at' => 'datetime',
            'last_attempted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CallAssignment $assignment): void {
            $tenantId = app(TenantContext::class)->id()
                ?? $assignment->tenant_id;

            if ($tenantId === null) {
                throw new LogicException(
                    'A call assignment must belong to a tenant.'
                );
            }

            if (
                ! in_array(
                    $assignment->priority,
                    self::PRIORITIES,
                    true
                )
            ) {
                throw new LogicException(
                    'The call assignment priority is invalid.'
                );
            }

            if (
                ! in_array(
                    $assignment->status,
                    self::STATUSES,
                    true
                )
            ) {
                throw new LogicException(
                    'The call assignment status is invalid.'
                );
            }

            self::ensureRelatedModelBelongsToTenant(
                CallQueue::class,
                $assignment->call_queue_id,
                (int) $tenantId,
                'The call queue must belong to the same tenant.'
            );

            self::ensureRelatedModelBelongsToTenant(
                Contact::class,
                $assignment->contact_id,
                (int) $tenantId,
                'The contact must belong to the same tenant.'
            );

            self::ensureRelatedModelBelongsToTenant(
                User::class,
                $assignment->assigned_to_user_id,
                (int) $tenantId,
                'The assigned agent must belong to the same tenant.'
            );

            self::ensureRelatedModelBelongsToTenant(
                User::class,
                $assignment->assigned_by_user_id,
                (int) $tenantId,
                'The assigning user must belong to the same tenant.'
            );

            if (
                in_array(
                    $assignment->status,
                    ['in_progress', 'completed'],
                    true
                )
                && $assignment->assigned_to_user_id === null
            ) {
                throw new LogicException(
                    'An active or completed assignment must have an agent.'
                );
            }

            if (
                $assignment->status === 'in_progress'
                && $assignment->claimed_at === null
            ) {
                $assignment->claimed_at = now();
            }

            if (
                $assignment->status === 'completed'
                && $assignment->completed_at === null
            ) {
                $assignment->completed_at = now();
            }

            if ($assignment->status !== 'completed') {
                $assignment->completed_at = null;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function callQueue(): BelongsTo
    {
        return $this->belongsTo(CallQueue::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'assigned_to_user_id'
        );
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'assigned_by_user_id'
        );
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(CallAttempt::class);
    }

    private static function ensureRelatedModelBelongsToTenant(
        string $modelClass,
        ?int $modelId,
        int $tenantId,
        string $message
    ): void {
        if ($modelId === null) {
            return;
        }

        $relatedTenantId = $modelClass::withoutGlobalScopes()
            ->whereKey($modelId)
            ->value('tenant_id');

        if ((int) $relatedTenantId !== $tenantId) {
            throw new LogicException($message);
        }
    }
}
