<?php

namespace App\Http\Requests;

use App\Models\PollingStation;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePollingStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            PollingStation::class
        ) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'tenant_id' => [
                'prohibited',
            ],
            'polling_center_id' => [
                'required',
                'integer',
                Rule::exists('polling_centers', 'id')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $tenantId
                        )
                    ),
            ],
            'station_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique(
                    'polling_stations',
                    'station_number'
                )->where(
                    fn ($query) => $query->where(
                        'polling_center_id',
                        $this->input('polling_center_id')
                    )
                ),
            ],
            'name_en' => [
                'nullable',
                'string',
                'max:255',
            ],
            'name_ar' => [
                'nullable',
                'string',
                'max:255',
            ],
            'room' => [
                'nullable',
                'string',
                'max:255',
            ],
            'registered_voters' => [
                'nullable',
                'integer',
                'min:0',
                'max:4294967295',
            ],
        ];
    }
}
