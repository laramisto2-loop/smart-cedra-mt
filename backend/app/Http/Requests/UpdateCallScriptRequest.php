<?php

namespace App\Http\Requests;

use App\Models\CallScript;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCallScriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $callScript = $this->route('callScript');

        return $callScript instanceof CallScript
            && ($this->user()?->can(
                'update',
                $callScript
            ) ?? false);
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $callScript = $this->route('callScript');

        $uniqueCode = Rule::unique(
            'call_scripts',
            'code'
        )->where(
            fn ($query) => $query->where(
                'tenant_id',
                $tenantId
            )
        );

        if ($callScript instanceof CallScript) {
            $uniqueCode->ignore($callScript->id);
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
            'language_code' => [
                'sometimes',
                'required',
                'string',
                'max:10',
                'regex:/^[a-z]{2,3}(?:[-_][A-Z]{2})?$/',
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],
            'body' => [
                'sometimes',
                'required',
                'string',
                'max:10000',
            ],

            // Ownership and workflow changes use server-controlled actions.
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
