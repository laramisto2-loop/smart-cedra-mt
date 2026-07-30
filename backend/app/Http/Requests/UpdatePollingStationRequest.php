<?php

namespace App\Http\Requests;

use App\Models\PollingStation;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePollingStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pollingStation = $this->route('polling_station');

        return $pollingStation instanceof PollingStation
            && ($this->user()?->can(
                'update',
                $pollingStation
            ) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $pollingStation = $this->route('polling_station');

        $pollingCenterId = $this->input(
            'polling_center_id',
            $pollingStation instanceof PollingStation
                ? $pollingStation->polling_center_id
                : null
        );

        return [
            'tenant_id' => [
                'prohibited',
            ],
            'polling_center_id' => [
                'sometimes',
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
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique(
                    'polling_stations',
                    'station_number'
                )
                    ->where(
                        fn ($query) => $query->where(
                            'polling_center_id',
                            $pollingCenterId
                        )
                    )
                    ->ignore(
                        $pollingStation instanceof PollingStation
                            ? $pollingStation->id
                            : null
                    ),
            ],
            'name_en' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'name_ar' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'room' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'registered_voters' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'max:4294967295',
            ],
        ];
    }
}
