<?php

namespace App\Http\Requests;

use App\Models\Incident;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $incident = $this->route('incident');

        return $incident instanceof Incident
            && ($this->user()?->can(
                'assign',
                $incident
            ) ?? false);
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'assigned_to_user_id' => [
                'required',
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
            'reviewed_by_user_id' => [
                'prohibited',
            ],
            'status' => [
                'prohibited',
            ],
            'sync_version' => [
                'prohibited',
            ],
        ];
    }
}
