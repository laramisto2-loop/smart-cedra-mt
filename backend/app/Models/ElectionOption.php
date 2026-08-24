<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\EnsuresParentBelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ElectionOption extends Model
{
    use Auditable;
    use BelongsToTenant;
    use EnsuresParentBelongsToTenant;
    use HasFactory;

    public const TYPE_CANDIDATE = 'candidate';

    public const TYPE_LIST = 'list';

    public const TYPE_BLANK = 'blank';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_CANDIDATE,
        self::TYPE_LIST,
        self::TYPE_BLANK,
        self::TYPE_OTHER,
    ];

    protected $fillable = [
        'tenant_id',
        'election_contest_id',
        'code',
        'name',
        'option_type',
        'ballot_order',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'ballot_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ElectionOption $option): void {
            if (blank($option->option_type)) {
                $option->option_type = self::TYPE_CANDIDATE;
            }
            if (! in_array($option->option_type, self::TYPES, true)) {
                throw new LogicException(
                    'The election option type is invalid.'
                );
            }

            if (
                $option->ballot_order !== null
                && $option->ballot_order < 1
            ) {
                throw new LogicException(
                    'The ballot order must be a positive number.'
                );
            }
        });

        static::updating(function (ElectionOption $option): void {
            if ($option->isDirty('tenant_id')) {
                throw new LogicException(
                    'An election option cannot be moved to another tenant.'
                );
            }

            if (
                $option->tallyResults()->exists()
                && $option->isDirty([
                    'election_contest_id',
                    'code',
                    'name',
                    'option_type',
                    'ballot_order',
                ])
            ) {
                throw new LogicException(
                    'An election option used by tally results cannot be structurally modified.'
                );
            }
        });

        static::deleting(function (ElectionOption $option): void {
            if ($option->tallyResults()->exists()) {
                throw new LogicException(
                    'An election option used by tally results cannot be deleted.'
                );
            }
        });
    }

    protected function tenantParentClass(): string
    {
        return ElectionContest::class;
    }

    protected function tenantParentForeignKey(): string
    {
        return 'election_contest_id';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function contest(): BelongsTo
    {
        return $this->belongsTo(
            ElectionContest::class,
            'election_contest_id'
        );
    }

    public function tallyResults(): HasMany
    {
        return $this->hasMany(
            TallyResult::class,
            'election_option_id'
        );
    }
}
