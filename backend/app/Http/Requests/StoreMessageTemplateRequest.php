<?php

namespace App\Http\Requests;

use App\Models\MessageTemplate;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            MessageTemplate::class
        ) ?? false;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
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
                Rule::unique('message_templates', 'code')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $tenantId
                        )
                    ),
            ],
            'channel' => [
                'required',
                Rule::in(MessageTemplate::CHANNELS),
            ],
            'provider' => [
                'nullable',
                'string',
                'max:30',
            ],
            'provider_template_name' => [
                'nullable',
                'string',
                'max:191',
            ],
            'language_code' => [
                'required',
                'string',
                'max:10',
                'regex:/^[a-z]{2,3}(?:[-_][A-Z]{2})?$/',
            ],
            'category' => [
                'required',
                Rule::in(MessageTemplate::CATEGORIES),
            ],
            'body' => [
                'required',
                'string',
                'max:10000',
            ],
            'variables' => [
                'nullable',
                'array',
                'max:50',
            ],
            'variables.*' => [
                'required',
                'string',
                'max:100',
                'distinct',
                'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
            ],

            // These fields are controlled by the server.
            'tenant_id' => [
                'prohibited',
            ],
            'created_by_user_id' => [
                'prohibited',
            ],
            'status' => [
                'prohibited',
            ],
            'approved_at' => [
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

        if ($this->has('language_code')) {
            $this->merge([
                'language_code' => trim(
                    (string) $this->input('language_code')
                ),
            ]);
        }
    }
}
