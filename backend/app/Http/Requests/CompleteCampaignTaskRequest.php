<?php

namespace App\Http\Requests;

use App\Models\CampaignTask;
use Illuminate\Foundation\Http\FormRequest;

class CompleteCampaignTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campaignTask = $this->route('campaignTask');

        return $campaignTask instanceof CampaignTask
            && ($this->user()?->can(
                'complete',
                $campaignTask
            ) ?? false);
    }

    public function rules(): array
    {
        return [
            'completion_notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'status' => [
                'prohibited',
            ],
            'started_at' => [
                'prohibited',
            ],
            'completed_at' => [
                'prohibited',
            ],
            'assigned_to_user_id' => [
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
