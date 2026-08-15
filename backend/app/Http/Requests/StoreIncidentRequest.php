<?php

namespace App\Http\Requests;

use App\Models\Incident;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            Incident::class
        ) ?? false;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $areaId = $this->input('area_id');
        $pollingCenterId = $this->input('polling_center_id');
        $user = $this->user();

        return [
            'campaign_task_id' => [
                'nullable',
                'integer',
                Rule::exists('campaign_tasks', 'id')
                    ->where(function ($query) use (
                        $tenantId,
                        $user
                    ) {
                        $query->where('tenant_id', $tenantId);

                        if (
                            $user !== null
                            && ! $user->hasPermission('incidents.review')
                        ) {
                            $query->where(
                                'assigned_to_user_id',
                                $user->id
                            );
                        }

                        return $query;
                    }),
            ],
            'area_id' => [
                'nullable',
                'required_with:polling_center_id,polling_station_id',
                'integer',
                Rule::exists('areas', 'id')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $tenantId
                        )
                    ),
            ],
            'polling_center_id' => [
                'nullable',
                'required_with:polling_station_id',
                'integer',
                Rule::exists('polling_centers', 'id')
                    ->where(function ($query) use (
                        $tenantId,
                        $areaId
                    ) {
                        $query->where('tenant_id', $tenantId);

                        if (filled($areaId)) {
                            $query->where('area_id', $areaId);
                        }

                        return $query;
                    }),
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
                'sometimes',
                'nullable',
                'uuid',
            ],
            'title' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'required',
                'string',
                'max:10000',
            ],
            'category' => [
                'sometimes',
                'required',
                Rule::in(Incident::CATEGORIES),
            ],
            'severity' => [
                'sometimes',
                'required',
                Rule::in(Incident::SEVERITIES),
            ],
            'location_notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'occurred_at' => [
                'required',
                'date',
            ],
            'client_updated_at' => [
                'nullable',
                'date',
            ],

            // Server-controlled identity and workflow fields.
            'tenant_id' => [
                'prohibited',
            ],
            'reported_by_user_id' => [
                'prohibited',
            ],
            'assigned_to_user_id' => [
                'prohibited',
            ],
            'reviewed_by_user_id' => [
                'prohibited',
            ],
            'reference_code' => [
                'prohibited',
            ],
            'status' => [
                'prohibited',
            ],
            'reported_at' => [
                'prohibited',
            ],
            'reviewed_at' => [
                'prohibited',
            ],
            'resolved_at' => [
                'prohibited',
            ],
            'resolution_notes' => [
                'prohibited',
            ],
            'sync_version' => [
                'prohibited',
            ],
        ];
    }
}
