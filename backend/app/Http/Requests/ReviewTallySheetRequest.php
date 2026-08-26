<?php

namespace App\Http\Requests;

use App\Models\TallySheet;
use App\Models\TallySubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewTallySheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sheet = $this->route('tallySheet');

        return $sheet instanceof TallySheet
            && ($this->user()?->can('review', $sheet) ?? false);
    }

    public function rules(): array
    {
        $sheet = $this->route('tallySheet');

        return [
            'submission_id' => [
                Rule::requiredIf($sheet?->status === TallySheet::STATUS_DISCREPANCY),
                'nullable', 'integer',
                Rule::exists('tally_submissions', 'id')->where(
                    fn ($query) => $query
                        ->where('tally_sheet_id', $sheet?->id)
                        ->where('status', TallySubmission::STATUS_SUBMITTED)
                ),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
            'tenant_id' => ['prohibited'],
            'status' => ['prohibited'],
            'reviewed_by_user_id' => ['prohibited'],
            'reviewed_at' => ['prohibited'],
            'approved_submission_id' => ['prohibited'],
        ];
    }
}
