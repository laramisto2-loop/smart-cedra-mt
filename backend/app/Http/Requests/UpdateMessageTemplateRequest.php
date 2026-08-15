<?php

namespace App\Http\Requests;

use App\Models\MessageTemplate;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $messageTemplate = $this->route('messageTemplate');

        return $messageTemplate instanceof MessageTemplate
            && ($this->user()?->can(
                'update',
                $messageTemplate
            ) ?? false);
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $messageTemplate = $this->route('messageTemplate');

        $uniqueCode = Rule::unique(
            'message_templates',
            'code'
        )->where(
            fn ($query) => $query->where(
                'tenant_id',
                $tenantId
            )
        );

        if ($messageTemplate instanceof MessageTemplate) {
            $uniqueCode->ignore($messageTemplate->id);
        }

        return [
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
            'channel' => [
                'sometimes',
                'required',
                Rule::in(MessageTemplate::CHANNELS),
            ],
            'provider' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
            ],
            'provider_template_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:191',
            ],
            'language_code' => [
                'sometimes',
                'required',
                'string',
                'max:10',
                'regex:/^[a-z]{2,3}(?:[-_][A-Z]{2})?$/',
            ],
            'category' => [
                'sometimes',
                'required',
                Rule::in(MessageTemplate::CATEGORIES),
            ],
            'body' => [
                'sometimes',
                'required',
                'string',
                'max:10000',
            ],
            'variables' => [
                'sometimes',
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

            // Approval and ownership use dedicated server workflows.
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
