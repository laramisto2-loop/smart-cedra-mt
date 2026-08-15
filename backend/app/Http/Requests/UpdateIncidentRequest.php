<?php

namespace App\Http\Requests;

use App\Models\Incident;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $incident = $this->route('incident');

        return $incident instanceof Incident
            && ($this->user()?->can(
                'update',
                $incident
            ) ?? false);
    }

    public function rules(): array
    {
        $incident = $this->route('incident');
        $tenantId = app(TenantContext::class)->id();
        $user = $this->user();

        $areaId = $this->input(
            'area_id',
            $incident instanceof Incident
                ? $incident->area_id
                : null
        );

        $pollingCenterId = $this->input(
            'polling_center_id',
            $incident instanceof Incident
                ? $incident->polling_center_id
                : null
        );

        return [
            'campaign_task_id' => [
                'sometimes',
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
                'sometimes',
                'nullable',
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
                'sometimes',
                'nullable',
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
                'sometimes',
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
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'sometimes',
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
                'sometimes',
                'nullable',
                'string',
                'max:2000',
            ],
            'occurred_at' => [
                'sometimes',
                'required',
                'date',
            ],
            'client_updated_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'expected_sync_version' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
            ],

            // Immutable and workflow-controlled fields.
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
            'client_uuid' => [
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
