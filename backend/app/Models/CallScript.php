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

class CallScript extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    public const STATUSES = [
        'draft',
        'active',
        'archived',
    ];

    protected $fillable = [
        'tenant_id',
        'created_by_user_id',
        'name',
        'code',
        'language_code',
        'description',
        'body',
        'status',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CallScript $script): void {
            $tenantId = app(TenantContext::class)->id()
                ?? $script->tenant_id;

            if ($tenantId === null) {
                throw new LogicException(
                    'A call script must belong to a tenant.'
                );
            }

            if (
                ! in_array(
                    $script->status,
                    self::STATUSES,
                    true
                )
            ) {
                throw new LogicException(
                    'The call script status is invalid.'
                );
            }

            self::ensureCreatorBelongsToTenant(
                $script->created_by_user_id,
                (int) $tenantId
            );

            if (
                $script->status === 'active'
                && $script->activated_at === null
            ) {
                $script->activated_at = now();
            }

            if ($script->status === 'draft') {
                $script->activated_at = null;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }

    public function queues(): HasMany
    {
        return $this->hasMany(CallQueue::class);
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
                'The call script creator must belong to the same tenant.'
            );
        }
    }
}
//This model now enforces tenant isolation, valid workflow statuses, creator ownership, activation timestamps, auditing, and queue relationships