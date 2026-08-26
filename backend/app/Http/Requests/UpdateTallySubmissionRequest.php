<?php

namespace App\Http\Requests;

use App\Models\ElectionOption;
use App\Models\TallySubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTallySubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $submission = $this->route('tallySubmission');

        return $submission instanceof TallySubmission
            && ($this->user()?->can('update', $submission) ?? false);
    }

    public function rules(): array
    {
        return [
            'registered_voters' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'ballots_cast' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'valid_ballots' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'invalid_ballots' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'blank_ballots' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'entered_at' => ['sometimes', 'nullable', 'date'],
            'results' => ['sometimes', 'array', 'max:100'],
            'results.*.election_option_id' => ['required', 'integer', 'distinct:strict'],
            'results.*.votes' => ['required', 'integer', 'min:0'],
            'tenant_id' => ['prohibited'],
            'tally_sheet_id' => ['prohibited'],
            'entered_by_user_id' => ['prohibited'],
            'client_uuid' => ['prohibited'],
            'reference_code' => ['prohibited'],
            'entry_number' => ['prohibited'],
            'status' => ['prohibited'],
            'submitted_at' => ['prohibited'],
            'received_at' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->has('results')) {
                return;
            }

            $submission = $this->route('tallySubmission');
            $contestId = $submission->tallySheet()->value('election_contest_id');
            $optionIds = collect($this->input('results', []))
                ->pluck('election_option_id')
                ->map(fn ($id) => (int) $id);

            $validCount = ElectionOption::query()
                ->where('election_contest_id', $contestId)
                ->whereIn('id', $optionIds)
                ->count();

            if ($validCount !== $optionIds->count()) {
                $validator->errors()->add(
                    'results',
                    'Every result option must belong to the tally sheet contest.'
                );
            }
        });
    }
}
