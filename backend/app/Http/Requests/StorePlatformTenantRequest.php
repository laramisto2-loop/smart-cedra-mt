<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StorePlatformTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdministrator() === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => is_string($this->slug)
                ? strtolower(trim($this->slug))
                : $this->slug,
            'admin_email' => is_string($this->admin_email)
                ? strtolower(trim($this->admin_email))
                : $this->admin_email,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'slug' => [
                'required',
                'string',
                'lowercase',
                'alpha_dash',
                'max:255',
                Rule::unique('tenants', 'slug'),
            ],
            'status' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    'active',
                    'suspended',
                ]),
            ],
            'brand_name' => [
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
            'admin_name' => [
                'required',
                'string',
                'max:255',
            ],
            'admin_email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'admin_password' => [
                'required',
                'confirmed',
                Password::defaults(),
            ],
            'tenant_id' => [
                'prohibited',
            ],
            'is_platform_admin' => [
                'prohibited',
            ],
            'logo_path' => [
                'prohibited',
            ],
        ];
    }
}
