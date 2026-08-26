<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class TallyResult extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'tally_submission_id',
        'election_option_id',
        'votes',
    ];

    protected function casts(): array
    {
        return [
            'votes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (TallyResult $result): void {
            $tenantId = app(TenantContext::class)->id()
                ?? $result->tenant_id;

            if ($tenantId === null) {
                throw new LogicException(
                    'A tally result must belong to a tenant.'
                );
            }

            $result->tenant_id = (int) $tenantId;

            if ($result->tally_submission_id === null) {
                throw new LogicException(
                    'A tally result must belong to a tally submission.'
                );
            }

            if ($result->election_option_id === null) {
                throw new LogicException(
                    'A tally result must belong to an election option.'
                );
            }

            if ($result->votes === null || (int) $result->votes < 0) {
                throw new LogicException(
                    'The tally result vote count cannot be negative.'
                );
            }

            $submission = self::findSubmission(
                $result->tally_submission_id,
                (int) $tenantId
            );

            $option = self::findOption(
                $result->election_option_id,
                (int) $tenantId
            );

            self::ensureContestIsConsistent(
                $submission,
                $option
            );

            if ($submission->isSubmitted()) {
                throw new LogicException(
                    'Results belonging to a submitted tally entry cannot be changed.'
                );
            }
        });

        static::updating(function (TallyResult $result): void {
            if (
                $result->isDirty([
                    'tenant_id',
                    'tally_submission_id',
                    'election_option_id',
                ])
            ) {
                throw new LogicException(
                    'The tally result identity cannot be modified.'
                );
            }
        });

        static::deleting(function (TallyResult $result): void {
            $submission = TallySubmission::withoutGlobalScopes()
                ->whereKey($result->tally_submission_id)
                ->first();

            if (
                $submission !== null
                && $submission->isSubmitted()
            ) {
                throw new LogicException(
                    'Results belonging to a submitted tally entry cannot be deleted.'
                );
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tallySubmission(): BelongsTo
    {
        return $this->belongsTo(TallySubmission::class);
    }

    public function electionOption(): BelongsTo
    {
        return $this->belongsTo(ElectionOption::class);
    }

    private static function findSubmission(
        int $submissionId,
        int $tenantId
    ): TallySubmission {
        $submission = TallySubmission::withoutGlobalScopes()
            ->whereKey($submissionId)
            ->first();

        if (
            $submission === null
            || (int) $submission->tenant_id !== $tenantId
        ) {
            throw new LogicException(
                'The tally result submission must belong to the same tenant.'
            );
        }

        return $submission;
    }

    private static function findOption(
        int $optionId,
        int $tenantId
    ): ElectionOption {
        $option = ElectionOption::withoutGlobalScopes()
            ->whereKey($optionId)
            ->first();

        if (
            $option === null
            || (int) $option->tenant_id !== $tenantId
        ) {
            throw new LogicException(
                'The tally result election option must belong to the same tenant.'
            );
        }

        return $option;
    }

    private static function ensureContestIsConsistent(
        TallySubmission $submission,
        ElectionOption $option
    ): void {
        $contestId = TallySheet::withoutGlobalScopes()
            ->whereKey($submission->tally_sheet_id)
            ->value('election_contest_id');

        if (
            $contestId === null
            || (int) $contestId
                !== (int) $option->election_contest_id
        ) {
            throw new LogicException(
                'The tally result option must belong to the tally sheet contest.'
            );
        }
    }
}
