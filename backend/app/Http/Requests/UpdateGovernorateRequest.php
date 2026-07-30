<?php

namespace App\Http\Requests;

use App\Models\Governorate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGovernorateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $governorate = $this->route('governorate');

        return $governorate instanceof Governorate
            && ($this->user()?->can('update', $governorate) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $governorate = $this->route('governorate');
        $tenantId = $this->user()?->tenant_id;

        $governorateId = $governorate instanceof Governorate
            ? $governorate->id
            : null;

        return [
            'tenant_id' => ['prohibited'],
            'name_en' => ['sometimes', 'required', 'string', 'max:255'],
            'name_ar' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('governorates', 'code')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $tenantId
                        )
                    )
                    ->ignore($governorateId),
            ],
        ];
    }
}
