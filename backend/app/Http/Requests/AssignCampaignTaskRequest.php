<?php

namespace App\Http\Requests;

use App\Models\CampaignTask;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignCampaignTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campaignTask = $this->route('campaignTask');

        return $campaignTask instanceof CampaignTask
            && ($this->user()?->can(
                'assign',
                $campaignTask
            ) ?? false);
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'assigned_to_user_id' => [
                'present',
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
            'tenant_id' => [
                'prohibited',
            ],
            'created_by_user_id' => [
                'prohibited',
            ],
        ];
    }
}
