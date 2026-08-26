<?php

namespace App\Services;

use App\Models\TallySheet;
use App\Models\TallySubmission;

class TallyReconciliationService
{
    public function reconcile(TallySheet $sheet): TallySheet
    {
        $submissions = $sheet->submissions()
            ->where('status', TallySubmission::STATUS_SUBMITTED)
            ->with('results')
            ->orderBy('entry_number')
            ->get();

        if ($submissions->count() < 2) {
            $sheet->update([
                'status' => TallySheet::STATUS_AWAITING_SECOND_ENTRY,
                'submitted_at' => $submissions->first()?->submitted_at ?? now(),
                'reconciliation_notes' => 'The first tally entry was submitted. An independent second entry is required.',
            ]);

            return $sheet->refresh();
        }

        $first = $submissions->first();
        $second = $submissions->last();
        $differences = $this->differences($first, $second);

        $sheet->update([
            'status' => $differences === []
                ? TallySheet::STATUS_READY_FOR_REVIEW
                : TallySheet::STATUS_DISCREPANCY,
            'submitted_at' => $sheet->submitted_at ?? now(),
            'reconciliation_notes' => $differences === []
                ? 'Double-entry validation passed: both submitted entries match.'
                : 'Double-entry discrepancy detected in: '.implode(', ', $differences).'.',
        ]);

        return $sheet->refresh();
    }

    /**
     * @return array<int, string>
     */
    private function differences(
        TallySubmission $first,
        TallySubmission $second
    ): array {
        $differences = [];
        $countFields = [
            'registered_voters',
            'ballots_cast',
            'valid_ballots',
            'invalid_ballots',
            'blank_ballots',
        ];

        foreach ($countFields as $field) {
            if ((int) $first->{$field} !== (int) $second->{$field}) {
                $differences[] = str_replace('_', ' ', $field);
            }
        }

        $firstResults = $first->results
            ->pluck('votes', 'election_option_id')
            ->map(fn ($votes) => (int) $votes)
            ->sortKeys()
            ->all();
        $secondResults = $second->results
            ->pluck('votes', 'election_option_id')
            ->map(fn ($votes) => (int) $votes)
            ->sortKeys()
            ->all();

        if ($firstResults !== $secondResults) {
            $differences[] = 'option vote totals';
        }

        return $differences;
    }
}
