<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CampaignTask extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    public const TYPES = [
        'general',
        'follow_up',
        'phone_call',
        'message',
        'field_visit',
        'data_entry',
    ];

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
        'cancelled',
    ];

    protected $fillable = [
        'tenant_id',
        'contact_id',
        'area_id',
        'created_by_user_id',
        'assigned_to_user_id',
        'title',
        'description',
        'type',
        'priority',
        'status',
        'due_at',
        'started_at',
        'completed_at',
        'completion_notes',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CampaignTask $task): void {
            $tenantId = app(TenantContext::class)->id()
                ?? $task->tenant_id;

            if ($tenantId === null) {
                throw new LogicException(
                    'A campaign task must belong to a tenant.'
                );
            }

            self::ensureContactBelongsToTenant(
                $task->contact_id,
                (int) $tenantId
            );

            self::ensureAreaBelongsToTenant(
                $task->area_id,
                (int) $tenantId
            );

            self::ensureUserBelongsToTenant(
                $task->created_by_user_id,
                (int) $tenantId,
                'creator'
            );

            self::ensureUserBelongsToTenant(
                $task->assigned_to_user_id,
                (int) $tenantId,
                'assignee'
            );

            if (
                $task->status === 'in_progress'
                && $task->started_at === null
            ) {
                $task->started_at = now();
            }

            if (
                $task->status === 'completed'
                && $task->completed_at === null
            ) {
                $task->completed_at = now();
            }

            if ($task->status !== 'completed') {
                $task->completed_at = null;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'assigned_to_user_id'
        );
    }

    private static function ensureContactBelongsToTenant(
        ?int $contactId,
        int $tenantId
    ): void {
        if ($contactId === null) {
            return;
        }

        $contactTenantId = Contact::withoutGlobalScopes()
            ->whereKey($contactId)
            ->value('tenant_id');

        if ((int) $contactTenantId !== $tenantId) {
            throw new LogicException(
                'The task contact must belong to the same tenant.'
            );
        }
    }

    private static function ensureAreaBelongsToTenant(
        ?int $areaId,
        int $tenantId
    ): void {
        if ($areaId === null) {
            return;
        }

        $areaTenantId = Area::withoutGlobalScopes()
            ->whereKey($areaId)
            ->value('tenant_id');

        if ((int) $areaTenantId !== $tenantId) {
            throw new LogicException(
                'The task area must belong to the same tenant.'
            );
        }
    }

    private static function ensureUserBelongsToTenant(
        ?int $userId,
        int $tenantId,
        string $relationship
    ): void {
        if ($userId === null) {
            return;
        }

        $userTenantId = User::withoutGlobalScopes()
            ->whereKey($userId)
            ->value('tenant_id');

        if ((int) $userTenantId !== $tenantId) {
            throw new LogicException(
                "The task {$relationship} must belong to the same tenant."
            );
        }
    }
}
