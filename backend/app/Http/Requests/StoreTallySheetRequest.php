<?php

namespace App\Http\Requests;

use App\Models\ElectionContest;
use App\Models\PollingStation;
use App\Models\TallySheet;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTallySheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TallySheet::class) ?? false;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'election_contest_id' => [
                'required', 'integer',
                Rule::exists('election_contests', 'id')->where(
                    fn ($query) => $query
                        ->where('tenant_id', $tenantId)
                        ->where('status', ElectionContest::STATUS_ACTIVE)
                ),
            ],
            'polling_center_id' => [
                'required', 'integer',
                Rule::exists('polling_centers', 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
            'polling_station_id' => [
                'required', 'integer',
                Rule::exists('polling_stations', 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
                Rule::unique('tally_sheets', 'polling_station_id')->where(
                    fn ($query) => $query->where(
                        'election_contest_id',
                        $this->integer('election_contest_id')
                    )
                ),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
            'tenant_id' => ['prohibited'],
            'created_by_user_id' => ['prohibited'],
            'reviewed_by_user_id' => ['prohibited'],
            'approved_by_user_id' => ['prohibited'],
            'approved_submission_id' => ['prohibited'],
            'reference_code' => ['prohibited'],
            'status' => ['prohibited'],
            'reconciliation_notes' => ['prohibited'],
            'submitted_at' => ['prohibited'],
            'reviewed_at' => ['prohibited'],
            'approved_at' => ['prohibited'],
            'rejected_at' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $belongsToCenter = PollingStation::query()
                ->whereKey($this->integer('polling_station_id'))
                ->where('polling_center_id', $this->integer('polling_center_id'))
                ->exists();

            if (! $belongsToCenter) {
                $validator->errors()->add(
                    'polling_station_id',
                    'The polling station must belong to the selected polling center.'
                );
            }
        });
    }
}
