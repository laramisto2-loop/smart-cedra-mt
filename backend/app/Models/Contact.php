<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Contact extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    public const STATUSES = [
        'active',
        'inactive',
        'archived',
    ];

    protected $fillable = [
        'tenant_id',
        'area_id',
        'created_by_user_id',
        'reference_code',
        'first_name',
        'last_name',
        'name_ar',
        'phone',
        'email',
        'address',
        'preferred_language',
        'preferred_channel',
        'status',
        'source',
        'notes',
    ];

    protected static function booted(): void
    {
        static::saving(function (Contact $contact): void {
            $tenantId = app(TenantContext::class)->id()
                ?? $contact->tenant_id;

            if ($tenantId === null) {
                throw new LogicException(
                    'A contact must belong to a tenant.'
                );
            }

            self::ensureAreaBelongsToTenant(
                $contact->area_id,
                (int) $tenantId
            );

            self::ensureCreatorBelongsToTenant(
                $contact->created_by_user_id,
                (int) $tenantId
            );
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
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

    public function consents(): HasMany
    {
        return $this->hasMany(ContactConsent::class);
    }

    public function segments(): BelongsToMany
    {
        return $this->belongsToMany(Segment::class)
            ->using(ContactSegment::class)
            ->as('membership')
            ->withPivot([
                'id',
                'tenant_id',
                'added_by_user_id',
                'added_at',
            ])
            ->withTimestamps();
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
                'The contact area must belong to the same tenant.'
            );
        }
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
                'The contact creator must belong to the same tenant.'
            );
        }
    }
}
