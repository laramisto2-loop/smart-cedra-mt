<?php

namespace App\Http\Requests;

use App\Models\CallAssignment;
use App\Models\CallQueue;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignCallQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        $callQueue = $this->route('callQueue');

        return $callQueue instanceof CallQueue
            && ($this->user()?->can(
                'assign',
                $callQueue
            ) ?? false);
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $callQueue = $this->route('callQueue');

        return [
            'contact_ids' => [
                'required',
                'array',
                'min:1',
                'max:500',
            ],
            'contact_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('contacts', 'id')
                    ->where(
                        fn ($query) => $query
                            ->where(
                                'tenant_id',
                                $tenantId
                            )
                            ->where(
                                'status',
                                'active'
                            )
                    ),
                Rule::unique(
                    'call_assignments',
                    'contact_id'
                )->where(
                    fn ($query) => $query
                        ->where(
                            'tenant_id',
                            $tenantId
                        )
                        ->where(
                            'call_queue_id',
                            $callQueue instanceof CallQueue
                                ? $callQueue->id
                                : null
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
            'priority' => [
                'sometimes',
                'required',
                Rule::in(CallAssignment::PRIORITIES),
            ],
            'scheduled_for' => [
                'nullable',
                'date',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],

            // Queue, ownership, and workflow fields come from the server.
            'tenant_id' => [
                'prohibited',
            ],
            'call_queue_id' => [
                'prohibited',
            ],
            'assigned_by_user_id' => [
                'prohibited',
            ],
            'status' => [
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
