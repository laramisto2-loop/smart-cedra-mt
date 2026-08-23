<?php

namespace App\Http\Requests;

use App\Models\CallAssignment;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCallAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $callAssignment = $this->route('callAssignment');

        return $callAssignment instanceof CallAssignment
            && ($this->user()?->can(
                'update',
                $callAssignment
            ) ?? false);
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        $mayManageAssignment = $this->user()?->hasRole(
            'tenant_admin'
        ) || $this->user()?->hasRole('coordinator');

        return [
            'assigned_to_user_id' => [
                'sometimes',
                Rule::prohibitedIf(
                    ! $mayManageAssignment
                ),
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
            'priority' => [
                'sometimes',
                Rule::prohibitedIf(
                    ! $mayManageAssignment
                ),
                'required',
                Rule::in(CallAssignment::PRIORITIES),
            ],
            'scheduled_for' => [
                'sometimes',
                Rule::prohibitedIf(
                    ! $mayManageAssignment
                ),
                'nullable',
                'date',
            ],
            'status' => [
                'sometimes',
                'required',
                Rule::in([
                    'completed',
                    'skipped',
                    'cancelled',
                ]),
            ],
            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            // Relationships and workflow timestamps are server-controlled.
            'tenant_id' => [
                'prohibited',
            ],
            'call_queue_id' => [
                'prohibited',
            ],
            'contact_id' => [
                'prohibited',
            ],
            'assigned_by_user_id' => [
                'prohibited',
            ],
            'claimed_at' => [
                'prohibited',
            ],
            'last_attempted_at' => [
                'prohibited',
            ],
            'completed_at' => [
                'prohibited',
            ],
        ];
    }
}
