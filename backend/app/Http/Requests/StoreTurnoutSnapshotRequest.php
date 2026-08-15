<?php

namespace App\Http\Requests;

use App\Models\TurnoutSnapshot;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTurnoutSnapshotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            TurnoutSnapshot::class
        ) ?? false;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $pollingCenterId = $this->input('polling_center_id');

        return [
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
            'polling_station_id' => [
                'nullable',
                'integer',
                Rule::exists('polling_stations', 'id')
                    ->where(function ($query) use (
                        $tenantId,
                        $pollingCenterId
                    ) {
                        $query->where('tenant_id', $tenantId);

                        if (filled($pollingCenterId)) {
                            $query->where(
                                'polling_center_id',
                                $pollingCenterId
                            );
                        }

                        return $query;
                    }),
            ],
            'client_uuid' => [
                'required',
                'uuid',
            ],
            'registered_voters' => [
                'nullable',
                'integer',
                'min:0',
                'max:4294967295',
            ],
            'turnout_count' => [
                'required',
                'integer',
                'min:0',
                'max:4294967295',
                Rule::when(
                    $this->filled('registered_voters'),
                    ['lte:registered_voters']
                ),
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'captured_at' => [
                'required',
                'date',
            ],

            // Identity and audit fields are controlled by the server.
            'tenant_id' => [
                'prohibited',
            ],
            'reported_by_user_id' => [
                'prohibited',
            ],
            'reference_code' => [
                'prohibited',
            ],
            'source' => [
                'prohibited',
            ],
            'received_at' => [
                'prohibited',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'turnout_count.lte' => (
                'The turnout count cannot exceed the registered voter count.'
            ),
        ];
    }
}
