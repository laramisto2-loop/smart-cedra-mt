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

class ElectionContest extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_CLOSED,
    ];

    protected $fillable = [
        'tenant_id',
        'created_by_user_id',
        'activated_by_user_id',
        'code',
        'name',
        'description',
        'election_date',
        'status',
        'activated_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'election_date' => 'date',
            'activated_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ElectionContest $contest): void {
            $tenantId = app(TenantContext::class)->id()
                    ?? $contest->tenant_id;

            if ($tenantId === null) {
                throw new LogicException(
                    'An election contest must belong to a tenant.'
                );
            }

            $contest->tenant_id = (int) $tenantId;

            if (blank($contest->status)) {
                $contest->status = self::STATUS_DRAFT;
            }
            self::ensureStatusIsValid($contest->status);
            self::ensureUserBelongsToTenant(
                $contest,
                $contest->created_by_user_id,
                'creator'
            );
            self::ensureUserBelongsToTenant(
                $contest,
                $contest->activated_by_user_id,
                'activator'
            );

            if (
                $contest->status === self::STATUS_ACTIVE
                && $contest->activated_at === null
            ) {
                $contest->activated_at = now();
            }

            if (
                $contest->status === self::STATUS_CLOSED
                && $contest->closed_at === null
            ) {
                $contest->closed_at = now();
            }
        });

        static::updating(function (ElectionContest $contest): void {
            if ($contest->isDirty('tenant_id')) {
                throw new LogicException(
                    'An election contest cannot be moved to another tenant.'
                );
            }

            if (
                $contest->getOriginal('status') === self::STATUS_CLOSED
                && $contest->isDirty()
            ) {
                throw new LogicException(
                    'A closed election contest cannot be modified.'
                );
            }
        });

        static::deleting(function (ElectionContest $contest): void {
            if (
                $contest->options()->exists()
                || $contest->tallySheets()->exists()
            ) {
                throw new LogicException(
                    'An election contest with options or tally sheets cannot be deleted.'
                );
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

    public function activator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'activated_by_user_id'
        );
    }

    public function options(): HasMany
    {
        return $this->hasMany(
            ElectionOption::class,
            'election_contest_id'
        );
    }

    public function tallySheets(): HasMany
    {
        return $this->hasMany(
            TallySheet::class,
            'election_contest_id'
        );
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    private static function ensureStatusIsValid(?string $status): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw new LogicException(
                'The election contest status is invalid.'
            );
        }
    }

    private static function ensureUserBelongsToTenant(
        ElectionContest $contest,
        ?int $userId,
        string $relationship
    ): void {
        if ($userId === null) {
            return;
        }

        $userTenantId = User::withoutGlobalScopes()
            ->whereKey($userId)
            ->value('tenant_id');

        if (
            $userTenantId === null
            || (int) $userTenantId !== (int) $contest->tenant_id
        ) {
            throw new LogicException(
                "The election contest {$relationship} must belong to the same tenant."
            );
        }
    }
}
