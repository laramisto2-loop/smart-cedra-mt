<?php

namespace App\Http\Requests;

use App\Models\Incident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $incident = $this->route('incident');

        return $incident instanceof Incident
            && ($this->user()?->can(
                'review',
                $incident
            ) ?? false);
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    'in_review',
                    'resolved',
                    'dismissed',
                ]),
            ],
            'resolution_notes' => [
                'nullable',
                'required_if:status,resolved,dismissed',
                'string',
                'max:10000',
            ],
            'expected_sync_version' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
            ],
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
            'reviewed_at' => [
                'prohibited',
            ],
            'resolved_at' => [
                'prohibited',
            ],
            'sync_version' => [
                'prohibited',
            ],
        ];
    }
}
