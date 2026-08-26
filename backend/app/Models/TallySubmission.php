<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class TallySubmission extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
    ];

    public const ENTRY_NUMBERS = [
        1,
        2,
    ];

    protected $fillable = [
        'tenant_id',
        'tally_sheet_id',
        'entered_by_user_id',
        'client_uuid',
        'reference_code',
        'entry_number',
        'status',
        'registered_voters',
        'ballots_cast',
        'valid_ballots',
        'invalid_ballots',
        'blank_ballots',
        'notes',
        'entered_at',
        'submitted_at',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_number' => 'integer',
            'registered_voters' => 'integer',
            'ballots_cast' => 'integer',
            'valid_ballots' => 'integer',
            'invalid_ballots' => 'integer',
            'blank_ballots' => 'integer',
            'entered_at' => 'datetime',
            'submitted_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (TallySubmission $submission): void {
            $tenantId = app(TenantContext::class)->id()
                ?? $submission->tenant_id;

            if ($tenantId === null) {
                throw new LogicException(
                    'A tally submission must belong to a tenant.'
                );
            }

            $submission->tenant_id = (int) $tenantId;

            if ($submission->tally_sheet_id === null) {
                throw new LogicException(
                    'A tally submission must belong to a tally sheet.'
                );
            }

            if ($submission->entered_by_user_id === null) {
                throw new LogicException(
                    'A tally submission must record the user who entered it.'
                );
            }

            if (blank($submission->client_uuid)) {
                $submission->client_uuid = Str::uuid()->toString();
            }

            if (blank($submission->reference_code)) {
                $compactUuid = str_replace(
                    '-',
                    '',
                    (string) $submission->client_uuid
                );

                $submission->reference_code = 'ENTRY-'.strtoupper(
                    substr($compactUuid, 0, 12)
                );
            }

            if (blank($submission->status)) {
                $submission->status = self::STATUS_DRAFT;
            }

            if (! in_array($submission->status, self::STATUSES, true)) {
                throw new LogicException(
                    'The tally submission status is invalid.'
                );
            }

            if (
                ! in_array(
                    (int) $submission->entry_number,
                    self::ENTRY_NUMBERS,
                    true
                )
            ) {
                throw new LogicException(
                    'The tally submission entry number must be 1 or 2.'
                );
            }

            self::ensureRelationshipBelongsToTenant(
                TallySheet::class,
                $submission->tally_sheet_id,
                (int) $tenantId,
                'tally sheet'
            );

            self::ensureRelationshipBelongsToTenant(
                User::class,
                $submission->entered_by_user_id,
                (int) $tenantId,
                'entry user'
            );

            self::ensureCountsAreValid($submission);

            $submission->entered_at ??= now();
            $submission->received_at ??= now();

            if (
                $submission->status === self::STATUS_SUBMITTED
                && $submission->submitted_at === null
            ) {
                $submission->submitted_at = now();
            }
        });

        static::updating(function (
            TallySubmission $submission
        ): void {
            if (
                $submission->isDirty([
                    'tenant_id',
                    'tally_sheet_id',
                    'entered_by_user_id',
                    'client_uuid',
                    'reference_code',
                    'entry_number',
                ])
            ) {
                throw new LogicException(
                    'The tally submission identity cannot be modified.'
                );
            }

            if (
                $submission->getOriginal('status')
                === self::STATUS_SUBMITTED
                && $submission->isDirty()
            ) {
                throw new LogicException(
                    'Submitted tally entries are immutable. Record a replacement entry instead.'
                );
            }
        });

        static::deleting(function (
            TallySubmission $submission
        ): void {
            if (
                $submission->status !== self::STATUS_DRAFT
                || $submission->results()->exists()
            ) {
                throw new LogicException(
                    'Only an empty draft tally submission can be deleted.'
                );
            }

            $isApprovedSubmission = TallySheet::withoutGlobalScopes()
                ->where('approved_submission_id', $submission->id)
                ->exists();

            if ($isApprovedSubmission) {
                throw new LogicException(
                    'An approved tally submission cannot be deleted.'
                );
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tallySheet(): BelongsTo
    {
        return $this->belongsTo(TallySheet::class);
    }

    public function entrant(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'entered_by_user_id'
        );
    }

    public function results(): HasMany
    {
        return $this->hasMany(TallyResult::class);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
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
                "The tally submission {$relationship} must belong to the same tenant."
            );
        }
    }

    private static function ensureCountsAreValid(
        TallySubmission $submission
    ): void {
        $counts = [
            'registered voters' => $submission->registered_voters,
            'ballots cast' => $submission->ballots_cast,
            'valid ballots' => $submission->valid_ballots,
            'invalid ballots' => $submission->invalid_ballots,
            'blank ballots' => $submission->blank_ballots,
        ];

        foreach ($counts as $label => $count) {
            if ($count !== null && (int) $count < 0) {
                throw new LogicException(
                    "The {$label} count cannot be negative."
                );
            }
        }

        if ($submission->status !== self::STATUS_SUBMITTED) {
            return;
        }

        foreach ($counts as $label => $count) {
            if ($count === null) {
                throw new LogicException(
                    "The {$label} count is required before submission."
                );
            }
        }

        if (
            (int) $submission->ballots_cast
            > (int) $submission->registered_voters
        ) {
            throw new LogicException(
                'Ballots cast cannot exceed registered voters.'
            );
        }

        $classifiedBallots =
            (int) $submission->valid_ballots
            + (int) $submission->invalid_ballots
            + (int) $submission->blank_ballots;

        if (
            $classifiedBallots
            !== (int) $submission->ballots_cast
        ) {
            throw new LogicException(
                'Valid, invalid, and blank ballots must equal ballots cast.'
            );
        }
    }
}
