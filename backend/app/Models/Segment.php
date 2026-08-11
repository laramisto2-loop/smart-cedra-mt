<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

class Segment extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    public const TYPES = [
        'static',
        'dynamic',
    ];

    public const STATUSES = [
        'active',
        'archived',
    ];

    protected $fillable = [
        'tenant_id',
        'created_by_user_id',
        'code',
        'name',
        'description',
        'type',
        'criteria',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Segment $segment): void {
            $tenantId = app(TenantContext::class)->id()
                ?? $segment->tenant_id;

            if ($tenantId === null) {
                throw new LogicException(
                    'A segment must belong to a tenant.'
                );
            }

            self::ensureCreatorBelongsToTenant(
                $segment->created_by_user_id,
                (int) $tenantId
            );
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

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class)
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
                'The segment creator must belong to the same tenant.'
            );
        }
    }
}
