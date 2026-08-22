<?php

namespace App\Http\Requests;

use App\Models\CallQueue;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCallQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        $callQueue = $this->route('callQueue');

        return $callQueue instanceof CallQueue
            && ($this->user()?->can(
                'update',
                $callQueue
            ) ?? false);
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $callQueue = $this->route('callQueue');

        $effectiveStatus = $this->input(
            'status',
            $callQueue instanceof CallQueue
                ? $callQueue->status
                : 'draft'
        );

        $uniqueCode = Rule::unique(
            'call_queues',
            'code'
        )->where(
            fn ($query) => $query->where(
                'tenant_id',
                $tenantId
            )
        );

        if ($callQueue instanceof CallQueue) {
            $uniqueCode->ignore($callQueue->id);
        }

        return [
            'call_script_id' => [
                'sometimes',
                Rule::requiredIf(
                    $this->has('status')
                    && $effectiveStatus === 'active'
                ),
                'nullable',
                'integer',
                Rule::exists('call_scripts', 'id')
                    ->where(
                        function ($query) use (
                            $tenantId,
                            $effectiveStatus
                        ) {
                            $query->where(
                                'tenant_id',
                                $tenantId
                            );

                            if ($effectiveStatus === 'active') {
                                $query->where(
                                    'status',
                                    'active'
                                );
                            }
                        }
                    ),
            ],
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9_-]+$/',
                $uniqueCode,
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],
            'priority' => [
                'sometimes',
                'required',
                Rule::in(CallQueue::PRIORITIES),
            ],
            'status' => [
                'sometimes',
                'required',
                Rule::in(CallQueue::STATUSES),
            ],
            'starts_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'ends_at' => [
                'sometimes',
                'nullable',
                'date',
                'after_or_equal:starts_at',
            ],

            // Ownership fields are controlled by the server.
            'tenant_id' => [
                'prohibited',
            ],
            'created_by_user_id' => [
                'prohibited',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge([
                'code' => strtoupper(
                    trim((string) $this->input('code'))
                ),
            ]);
        }
    }
}
