<?php

namespace App\Http\Requests;

use App\Models\CallAttempt;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCallAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            CallAttempt::class
        ) ?? false;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $user = $this->user();

        $mayManageAssignments = $user?->hasRole(
            'tenant_admin'
        ) || $user?->hasRole('coordinator');

        return [
            'call_assignment_id' => [
                'required',
                'integer',
                Rule::exists('call_assignments', 'id')
                    ->where(function ($query) use (
                        $tenantId,
                        $user,
                        $mayManageAssignments
                    ) {
                        $query
                            ->where('tenant_id', $tenantId)
                            ->whereIn('status', [
                                'pending',
                                'in_progress',
                            ]);

                        if (
                            ! $mayManageAssignments
                            && $user !== null
                        ) {
                            $query->where(
                                'assigned_to_user_id',
                                $user->id
                            );
                        }

                        return $query;
                    }),
            ],
            'client_uuid' => [
                'required',
                'uuid',
            ],
            'outcome' => [
                'required',
                Rule::in(CallAttempt::OUTCOMES),
            ],
            'duration_seconds' => [
                'nullable',
                'integer',
                'min:0',
                'max:4294967295',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'attempted_at' => [
                'required',
                'date',
            ],
            'follow_up_at' => [
                'nullable',
                'required_if:outcome,callback_requested',
                'date',
                'after_or_equal:attempted_at',
            ],

            // Identity, audit, and generated follow-up fields are server-controlled.
            'tenant_id' => [
                'prohibited',
            ],
            'performed_by_user_id' => [
                'prohibited',
            ],
            'follow_up_task_id' => [
                'prohibited',
            ],
            'reference_code' => [
                'prohibited',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'follow_up_at.required_if' => (
                'A follow-up date and time is required when a callback is requested.'
            ),
            'follow_up_at.after_or_equal' => (
                'The follow-up date and time cannot be earlier than the call attempt.'
            ),
        ];
    }
}
