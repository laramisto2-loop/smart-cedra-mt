<?php

namespace App\Http\Requests;

use App\Models\ElectionOption;
use App\Models\TallySubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubmitTallySubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $submission = $this->route('tallySubmission');

        return $submission instanceof TallySubmission
            && ($this->user()?->can('submit', $submission) ?? false);
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['prohibited'],
            'status' => ['prohibited'],
            'submitted_at' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $submission = $this->route('tallySubmission');
            $submission->loadMissing(['results', 'tallySheet.contest.options']);

            $counts = [
                'registered_voters', 'ballots_cast', 'valid_ballots',
                'invalid_ballots', 'blank_ballots',
            ];

            foreach ($counts as $count) {
                if ($submission->{$count} === null) {
                    $validator->errors()->add($count, 'This count is required before submission.');
                }
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ((int) $submission->ballots_cast > (int) $submission->registered_voters) {
                $validator->errors()->add('ballots_cast', 'Ballots cast cannot exceed registered voters.');
            }

            $classified = (int) $submission->valid_ballots
                + (int) $submission->invalid_ballots
                + (int) $submission->blank_ballots;

            if ($classified !== (int) $submission->ballots_cast) {
                $validator->errors()->add(
                    'ballots_cast',
                    'Valid, invalid, and blank ballots must equal ballots cast.'
                );
            }

            $requiredOptionIds = $submission->tallySheet->contest->options
                ->where('is_active', true)
                ->whereNotIn('option_type', [ElectionOption::TYPE_BLANK])
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values();

            $resultOptionIds = $submission->results
                ->pluck('election_option_id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values();

            if ($requiredOptionIds->all() !== $resultOptionIds->all()) {
                $validator->errors()->add(
                    'results',
                    'A vote count is required for every active ballot option.'
                );
            }

            if ((int) $submission->results->sum('votes') !== (int) $submission->valid_ballots) {
                $validator->errors()->add(
                    'results',
                    'The option vote total must equal the valid ballot count.'
                );
            }
        });
    }
}
