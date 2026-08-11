<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use LogicException;

class ContactSegment extends Pivot
{
    use Auditable, BelongsToTenant;

    protected $table = 'contact_segment';

    public $incrementing = true;

    protected $fillable = [
        'tenant_id',
        'contact_id',
        'segment_id',
        'added_by_user_id',
        'added_at',
    ];

    protected function casts(): array
    {
        return [
            'added_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (
            ContactSegment $membership
        ): void {
            $tenantId = app(TenantContext::class)->id()
                ?? $membership->tenant_id;

            if ($tenantId === null) {
                throw new LogicException(
                    'A segment membership must belong to a tenant.'
                );
            }

            self::ensureContactBelongsToTenant(
                $membership->contact_id,
                (int) $tenantId
            );

            self::ensureSegmentBelongsToTenant(
                $membership->segment_id,
                (int) $tenantId
            );

            self::ensureAdderBelongsToTenant(
                $membership->added_by_user_id,
                (int) $tenantId
            );
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

    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'added_by_user_id'
        );
    }

    private static function ensureContactBelongsToTenant(
        int $contactId,
        int $tenantId
    ): void {
        $contactTenantId = Contact::withoutGlobalScopes()
            ->whereKey($contactId)
            ->value('tenant_id');

        if ((int) $contactTenantId !== $tenantId) {
            throw new LogicException(
                'The segment contact must belong to the same tenant.'
            );
        }
    }

    private static function ensureSegmentBelongsToTenant(
        int $segmentId,
        int $tenantId
    ): void {
        $segment = Segment::withoutGlobalScopes()
            ->whereKey($segmentId)
            ->first([
                'tenant_id',
                'type',
            ]);

        if (
            $segment === null
            || (int) $segment->tenant_id !== $tenantId
        ) {
            throw new LogicException(
                'The membership segment must belong to the same tenant.'
            );
        }

        if ($segment->type !== 'static') {
            throw new LogicException(
                'Only static segments accept manual memberships.'
            );
        }
    }

    private static function ensureAdderBelongsToTenant(
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
                'The membership creator must belong to the same tenant.'
            );
        }
    }
}
