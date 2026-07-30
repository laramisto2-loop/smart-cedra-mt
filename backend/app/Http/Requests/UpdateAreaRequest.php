<?php

namespace App\Http\Requests;

use App\Models\Area;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $area = $this->route('area');

        return $area instanceof Area
            && ($this->user()?->can('update', $area) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $area = $this->route('area');

        return [
            'tenant_id' => [
                'prohibited',
            ],
            'district_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('districts', 'id')
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
                Rule::unique('areas', 'code')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $tenantId
                        )
                    )
                    ->ignore(
                        $area instanceof Area
                            ? $area->id
                            : null
                    ),
            ],
            'type' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    'locality',
                    'city',
                    'town',
                    'village',
                    'municipality',
                    'neighbourhood',
                ]),
            ],
            'latitude' => [
                'sometimes',
                'nullable',
                'numeric',
                'between:-90,90',
            ],
            'longitude' => [
                'sometimes',
                'nullable',
                'numeric',
                'between:-180,180',
            ],
        ];
    }
}
