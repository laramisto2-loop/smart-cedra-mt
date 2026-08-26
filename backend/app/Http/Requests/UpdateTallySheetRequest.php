<?php

namespace App\Http\Requests;

use App\Models\PollingStation;
use App\Models\TallySheet;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTallySheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sheet = $this->route('tallySheet');

        return $sheet instanceof TallySheet
            && ($this->user()?->can('update', $sheet) ?? false);
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'polling_center_id' => [
                'sometimes', 'integer',
                Rule::exists('polling_centers', 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
            'polling_station_id' => [
                'sometimes', 'integer',
                Rule::exists('polling_stations', 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'tenant_id' => ['prohibited'],
            'election_contest_id' => ['prohibited'],
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

            $sheet = $this->route('tallySheet');
            $centerId = $this->integer('polling_center_id') ?: $sheet?->polling_center_id;
            $stationId = $this->integer('polling_station_id') ?: $sheet?->polling_station_id;

            $belongsToCenter = PollingStation::query()
                ->whereKey($stationId)
                ->where('polling_center_id', $centerId)
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
