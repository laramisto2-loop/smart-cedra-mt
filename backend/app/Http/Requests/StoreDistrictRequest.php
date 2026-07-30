<?php

namespace App\Http\Requests;

use App\Models\District;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDistrictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', District::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'tenant_id' => [
                'prohibited',
            ],
            'governorate_id' => [
                'required',
                'integer',
                Rule::exists('governorates', 'id')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $tenantId
                        )
                    ),
            ],
            'name_en' => [
                'required',
                'string',
                'max:255',
            ],
            'name_ar' => [
                'required',
                'string',
                'max:255',
            ],
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('districts', 'code')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $tenantId
                        )
                    ),
            ],
        ];
    }
}
