<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlatformTenantStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdministrator() === true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([
                    'active',
                    'suspended',
                ]),
            ],
            'tenant_id' => [
                'prohibited',
            ],
            'is_platform_admin' => [
                'prohibited',
            ],
        ];
    }
}
