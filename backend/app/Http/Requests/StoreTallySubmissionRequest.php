<?php

namespace App\Http\Requests;

use App\Models\ElectionOption;
use App\Models\TallySheet;
use App\Models\TallySubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTallySubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sheet = $this->route('tallySheet');

        return $sheet instanceof TallySheet
            && ($this->user()?->can('create', TallySubmission::class) ?? false)
            && ($this->user()?->can('view', $sheet) ?? false);
    }

    public function rules(): array
    {
        return [
            'client_uuid' => ['sometimes', 'nullable', 'uuid'],
            'entry_number' => ['required', 'integer', Rule::in(TallySubmission::ENTRY_NUMBERS)],
            'registered_voters' => ['nullable', 'integer', 'min:0'],
            'ballots_cast' => ['nullable', 'integer', 'min:0'],
            'valid_ballots' => ['nullable', 'integer', 'min:0'],
            'invalid_ballots' => ['nullable', 'integer', 'min:0'],
            'blank_ballots' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'entered_at' => ['nullable', 'date'],
            'results' => ['sometimes', 'array', 'max:100'],
            'results.*.election_option_id' => ['required', 'integer', 'distinct:strict'],
            'results.*.votes' => ['required', 'integer', 'min:0'],
            'tenant_id' => ['prohibited'],
            'tally_sheet_id' => ['prohibited'],
            'entered_by_user_id' => ['prohibited'],
            'reference_code' => ['prohibited'],
            'status' => ['prohibited'],
            'submitted_at' => ['prohibited'],
            'received_at' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $sheet = $this->route('tallySheet');
            $optionIds = collect($this->input('results', []))
                ->pluck('election_option_id')
                ->map(fn ($id) => (int) $id);

            if ($optionIds->isNotEmpty()) {
                $validCount = ElectionOption::query()
                    ->where('election_contest_id', $sheet->election_contest_id)
                    ->whereIn('id', $optionIds)
                    ->count();

                if ($validCount !== $optionIds->count()) {
                    $validator->errors()->add(
                        'results',
                        'Every result option must belong to the tally sheet contest.'
                    );
                }
            }

            $existingEntrant = TallySubmission::query()
                ->where('tally_sheet_id', $sheet->id)
                ->where('entry_number', '!=', $this->integer('entry_number'))
                ->value('entered_by_user_id');

            if ($existingEntrant !== null && (int) $existingEntrant === (int) $this->user()->id) {
                $validator->errors()->add(
                    'entry_number',
                    'The second tally entry must be recorded by a different user.'
                );
            }
        });
    }
}
