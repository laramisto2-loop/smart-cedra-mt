<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('settings.manage') === true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['brand_name', 'primary_color', 'timezone'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $this->merge([
                    $field => trim((string) $this->input($field)),
                ]);
            }
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'brand_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'primary_color' => [
                'sometimes',
                'required',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
            'timezone' => [
                'sometimes',
                'required',
                'string',
                'timezone',
                'max:255',
            ],
            'tenant_id' => ['prohibited'],
            'logo_path' => ['prohibited'],
        ];
    }
}
