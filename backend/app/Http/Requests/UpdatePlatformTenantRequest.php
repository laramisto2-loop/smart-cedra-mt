<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlatformTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdministrator() === true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('slug') && is_string($this->slug)) {
            $this->merge([
                'slug' => strtolower(trim($this->slug)),
            ]);
        }
    }

    public function rules(): array
    {
        $tenant = $this->route('tenant');

        $tenantId = $tenant instanceof Tenant
            ? $tenant->getKey()
            : $tenant;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'lowercase',
                'alpha_dash',
                'max:255',
                Rule::unique('tenants', 'slug')->ignore($tenantId),
            ],
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
            'status' => [
                'prohibited',
            ],
            'admin_name' => [
                'prohibited',
            ],
            'admin_email' => [
                'prohibited',
            ],
            'admin_password' => [
                'prohibited',
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
