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

class TallySheet extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_AWAITING_SECOND_ENTRY = 'awaiting_second_entry';

    public const STATUS_READY_FOR_REVIEW = 'ready_for_review';

    public const STATUS_DISCREPANCY = 'discrepancy';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_AWAITING_SECOND_ENTRY,
        self::STATUS_READY_FOR_REVIEW,
        self::STATUS_DISCREPANCY,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'tenant_id',
        'election_contest_id',
        'polling_center_id',
        'polling_station_id',
        'created_by_user_id',
        'reviewed_by_user_id',
        'approved_by_user_id',
        'approved_submission_id',
        'reference_code',
        'status',
        'notes',
        'reconciliation_notes',
        'submitted_at',
        'reviewed_at',
        'approved_at',
        'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (TallySheet $sheet): void {
            $tenantId = app(TenantContext::class)->id()
                ?? $sheet->tenant_id;

            if ($tenantId === null) {
                throw new LogicException(
                    'A tally sheet must belong to a tenant.'
                );
            }

            $sheet->tenant_id = (int) $tenantId;

            if (blank($sheet->reference_code)) {
                $compactUuid = str_replace(
                    '-',
                    '',
                    Str::uuid()->toString()
                );

                $sheet->reference_code = 'TALLY-'.strtoupper(
                    substr($compactUuid, 0, 12)
                );
            }

            if (blank($sheet->status)) {
                $sheet->status = self::STATUS_PENDING;
            }

            if (! in_array($sheet->status, self::STATUSES, true)) {
                throw new LogicException(
                    'The tally sheet status is invalid.'
                );
            }

            self::ensureRelationshipBelongsToTenant(
                ElectionContest::class,
                $sheet->election_contest_id,
                (int) $tenantId,
                'election contest'
            );

            self::ensureRelationshipBelongsToTenant(
                PollingCenter::class,
                $sheet->polling_center_id,
                (int) $tenantId,
                'polling center'
            );

            self::ensureRelationshipBelongsToTenant(
                PollingStation::class,
                $sheet->polling_station_id,
                (int) $tenantId,
                'polling station'
            );

            self::ensureRelationshipBelongsToTenant(
                User::class,
                $sheet->created_by_user_id,
                (int) $tenantId,
                'creator'
            );

            self::ensureRelationshipBelongsToTenant(
                User::class,
                $sheet->reviewed_by_user_id,
                (int) $tenantId,
                'reviewer'
            );

            self::ensureRelationshipBelongsToTenant(
                User::class,
                $sheet->approved_by_user_id,
                (int) $tenantId,
                'approver'
            );

            self::ensureGeographyIsConsistent($sheet);
            self::ensureApprovalIsValid($sheet, (int) $tenantId);

            if (
                $sheet->status === self::STATUS_APPROVED
                && $sheet->approved_at === null
            ) {
                $sheet->approved_at = now();
            }

            if (
                $sheet->status === self::STATUS_REJECTED
                && $sheet->rejected_at === null
            ) {
                $sheet->rejected_at = now();
            }
        });

        static::updating(function (TallySheet $sheet): void {
            if ($sheet->isDirty('tenant_id')) {
                throw new LogicException(
                    'A tally sheet cannot be moved to another tenant.'
                );
            }

            if (
                in_array(
                    $sheet->getOriginal('status'),
                    [
                        self::STATUS_APPROVED,
                        self::STATUS_REJECTED,
                    ],
                    true
                )
                && $sheet->isDirty()
            ) {
                throw new LogicException(
                    'A finalized tally sheet cannot be modified.'
                );
            }
        });

        static::deleting(function (TallySheet $sheet): void {
            if (
                $sheet->status !== self::STATUS_PENDING
                || $sheet->submissions()->exists()
                || $sheet->attachments()->exists()
            ) {
                throw new LogicException(
                    'Only an unused pending tally sheet can be deleted.'
                );
            }
        });
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

    public function pollingCenter(): BelongsTo
    {
        return $this->belongsTo(PollingCenter::class);
    }

    public function pollingStation(): BelongsTo
    {
        return $this->belongsTo(PollingStation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by_user_id'
        );
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'approved_by_user_id'
        );
    }

    public function approvedSubmission(): BelongsTo
    {
        return $this->belongsTo(
            TallySubmission::class,
            'approved_submission_id'
        );
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(TallySubmission::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TallySheetAttachment::class);
    }

    public function isFinalized(): bool
    {
        return in_array(
            $this->status,
            [
                self::STATUS_APPROVED,
                self::STATUS_REJECTED,
            ],
            true
        );
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
                "The tally sheet {$relationship} must belong to the same tenant."
            );
        }
    }

    private static function ensureGeographyIsConsistent(
        TallySheet $sheet
    ): void {
        if ($sheet->polling_station_id === null) {
            return;
        }

        if ($sheet->polling_center_id === null) {
            throw new LogicException(
                'A tally sheet with a polling station must include its polling center.'
            );
        }

        $stationCenterId = PollingStation::withoutGlobalScopes()
            ->whereKey($sheet->polling_station_id)
            ->value('polling_center_id');

        if (
            (int) $stationCenterId
            !== (int) $sheet->polling_center_id
        ) {
            throw new LogicException(
                'The tally sheet polling station must belong to the selected polling center.'
            );
        }
    }

    private static function ensureApprovalIsValid(
        TallySheet $sheet,
        int $tenantId
    ): void {
        if ($sheet->status !== self::STATUS_APPROVED) {
            return;
        }

        if (
            $sheet->approved_submission_id === null
            || $sheet->approved_by_user_id === null
            || $sheet->reviewed_by_user_id === null
            || $sheet->reviewed_at === null
        ) {
            throw new LogicException(
                'An approved tally sheet must record its review, approved submission, and approver.'
            );
        }

        if (
            (int) $sheet->approved_by_user_id
            === (int) $sheet->reviewed_by_user_id
        ) {
            throw new LogicException(
                'The tally reviewer and final approver must be different users.'
            );
        }

        $submission = TallySubmission::withoutGlobalScopes()
            ->whereKey($sheet->approved_submission_id)
            ->first();

        if (
            $submission === null
            || (int) $submission->tenant_id !== $tenantId
            || (int) $submission->tally_sheet_id !== (int) $sheet->id
        ) {
            throw new LogicException(
                'The approved submission must belong to this tally sheet and tenant.'
            );
        }

        $entryUserIds = TallySubmission::withoutGlobalScopes()
            ->where('tally_sheet_id', $sheet->id)
            ->pluck('entered_by_user_id')
            ->filter()
            ->map(fn ($userId) => (int) $userId);

        if (
            $entryUserIds->contains((int) $sheet->reviewed_by_user_id)
            || $entryUserIds->contains((int) $sheet->approved_by_user_id)
        ) {
            throw new LogicException(
                'Tally entry users cannot review or approve their own results.'
            );
        }
    }
}
