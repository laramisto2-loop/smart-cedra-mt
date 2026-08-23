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

class CallQueue extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    public const PRIORITIES = [
        'low',
        'normal',
        'high',
        'urgent',
    ];

    public const STATUSES = [
        'draft',
        'active',
        'paused',
        'completed',
        'archived',
    ];

    protected $fillable = [
        'tenant_id',
        'call_script_id',
        'created_by_user_id',
        'name',
        'code',
        'description',
        'priority',
        'status',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CallQueue $queue): void {
            $tenantId = app(TenantContext::class)->id()
                ?? $queue->tenant_id;

            if ($tenantId === null) {
                throw new LogicException(
                    'A call queue must belong to a tenant.'
                );
            }

            if (
                ! in_array(
                    $queue->priority,
                    self::PRIORITIES,
                    true
                )
            ) {
                throw new LogicException(
                    'The call queue priority is invalid.'
                );
            }

            if (
                ! in_array(
                    $queue->status,
                    self::STATUSES,
                    true
                )
            ) {
                throw new LogicException(
                    'The call queue status is invalid.'
                );
            }

            self::ensureCreatorBelongsToTenant(
                $queue->created_by_user_id,
                (int) $tenantId
            );

            self::ensureScriptBelongsToTenant(
                $queue->call_script_id,
                (int) $tenantId
            );

            if (
                $queue->starts_at !== null
                && $queue->ends_at !== null
                && $queue->ends_at->isBefore($queue->starts_at)
            ) {
                throw new LogicException(
                    'The call queue end time cannot be before its start time.'
                );
            }

            if ($queue->status === 'active') {
                if ($queue->call_script_id === null) {
                    throw new LogicException(
                        'An active call queue must have a call script.'
                    );
                }

                $scriptStatus = CallScript::query()
                    ->whereKey($queue->call_script_id)
                    ->value('status');

                if ($scriptStatus !== 'active') {
                    throw new LogicException(
                        'An active call queue must use an active call script.'
                    );
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function callScript(): BelongsTo
    {
        return $this->belongsTo(CallScript::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CallAssignment::class);
    }

    private static function ensureCreatorBelongsToTenant(
        ?int $userId,
        int $tenantId
    ): void {
        if ($userId === null) {
            return;
        }

        $userTenantId = User::query()
            ->whereKey($userId)
            ->value('tenant_id');

        if ((int) $userTenantId !== $tenantId) {
            throw new LogicException(
                'The call queue creator must belong to the same tenant.'
            );
        }
    }

    private static function ensureScriptBelongsToTenant(
        ?int $scriptId,
        int $tenantId
    ): void {
        if ($scriptId === null) {
            return;
        }

        $scriptTenantId = CallScript::withoutGlobalScopes()
            ->whereKey($scriptId)
            ->value('tenant_id');

        if ((int) $scriptTenantId !== $tenantId) {
            throw new LogicException(
                'The call script must belong to the same tenant.'
            );
        }
    }
}
