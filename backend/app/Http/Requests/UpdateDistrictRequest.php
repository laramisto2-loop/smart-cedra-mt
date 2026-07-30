<?php

namespace App\Http\Requests;

use App\Models\District;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDistrictRequest extends FormRequest
{
    public function authorize(): bool
    {
        $district = $this->route('district');

        return $district instanceof District
            && ($this->user()?->can('update', $district) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $district = $this->route('district');

        return [
            'tenant_id' => [
                'prohibited',
            ],
            'governorate_id' => [
                'sometimes',
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
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'name_ar' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('districts', 'code')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $tenantId
                        )
                    )
                    ->ignore(
                        $district instanceof District
                            ? $district->id
                            : null
                    ),
            ],
        ];
    }
}

// The migration confirms that each district requires:
// a tenant;
// a parent governorate;
// English and Arabic names;
// a code unique within its tenant.
