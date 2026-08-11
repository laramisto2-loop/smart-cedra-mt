<?php

namespace App\Http\Requests;

use App\Models\CampaignTask;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampaignTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            CampaignTask::class
        ) ?? false;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'contact_id' => [
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
            'assigned_to_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $tenantId
                        )
                    ),
            ],
            'title' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'type' => [
                'required',
                Rule::in(CampaignTask::TYPES),
            ],
            'priority' => [
                'required',
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
                'nullable',
                'date',
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
