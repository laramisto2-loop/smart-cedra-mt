<?php

namespace App\Http\Requests;

use App\Models\CallQueue;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCallQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            CallQueue::class
        ) ?? false;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $requestedStatus = $this->input('status', 'draft');

        return [
            'call_script_id' => [
                Rule::requiredIf(
                    $requestedStatus === 'active'
                ),
                'nullable',
                'integer',
                Rule::exists('call_scripts', 'id')
                    ->where(
                        function ($query) use (
                            $tenantId,
                            $requestedStatus
                        ) {
                            $query->where(
                                'tenant_id',
                                $tenantId
                            );

                            if ($requestedStatus === 'active') {
                                $query->where(
                                    'status',
                                    'active'
                                );
                            }
                        }
                    ),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('call_queues', 'code')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $tenantId
                        )
                    ),
            ],
            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'priority' => [
                'required',
                Rule::in(CallQueue::PRIORITIES),
            ],
            'status' => [
                'sometimes',
                Rule::in([
                    'draft',
                    'active',
                ]),
            ],
            'starts_at' => [
                'nullable',
                'date',
            ],
            'ends_at' => [
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
