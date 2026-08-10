<?php

namespace App\Http\Requests;

use App\Models\CampaignTask;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campaignTask = $this->route('campaignTask');

        return $campaignTask instanceof CampaignTask
            && ($this->user()?->can(
                'update',
                $campaignTask
            ) ?? false);
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'contact_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('contacts', 'id')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $tenantId
                        )
                    ),
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
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],
            'type' => [
                'sometimes',
                Rule::in(CampaignTask::TYPES),
            ],
            'priority' => [
                'sometimes',
                Rule::in(CampaignTask::PRIORITIES),
            ],
            'status' => [
                'sometimes',
                Rule::in([
                    'pending',
                    'in_progress',
                    'cancelled',
                ]),
            ],
            'due_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'assigned_to_user_id' => [
                'prohibited',
            ],
            'started_at' => [
                'prohibited',
            ],
            'completed_at' => [
                'prohibited',
            ],
            'completion_notes' => [
                'prohibited',
            ],
            'tenant_id' => [
                'prohibited',
            ],
            'created_by_user_id' => [
                'prohibited',
            ],
        ];
    }
}
