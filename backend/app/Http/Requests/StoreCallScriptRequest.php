<?php

namespace App\Http\Requests;

use App\Models\CallScript;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCallScriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            CallScript::class
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
                Rule::unique('call_scripts', 'code')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $tenantId
                        )
                    ),
            ],
            'language_code' => [
                'required',
                'string',
                'max:10',
                'regex:/^[a-z]{2,3}(?:[-_][A-Z]{2})?$/',
            ],
            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'body' => [
                'required',
                'string',
                'max:10000',
            ],

            // Ownership and workflow fields are controlled by the server.
            'tenant_id' => [
                'prohibited',
            ],
            'created_by_user_id' => [
                'prohibited',
            ],
            'status' => [
                'prohibited',
            ],
            'activated_at' => [
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
