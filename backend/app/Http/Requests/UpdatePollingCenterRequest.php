<?php

namespace App\Http\Requests;

use App\Models\PollingCenter;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePollingCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pollingCenter = $this->route('polling_center');

        return $pollingCenter instanceof PollingCenter
            && ($this->user()?->can(
                'update',
                $pollingCenter
            ) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $pollingCenter = $this->route('polling_center');

        return [
            'tenant_id' => [
                'prohibited',
            ],
            'area_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('areas', 'id')
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
                Rule::unique('polling_centers', 'code')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $tenantId
                        )
                    )
                    ->ignore(
                        $pollingCenter instanceof PollingCenter
                            ? $pollingCenter->id
                            : null
                    ),
            ],
            'address_en' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'address_ar' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
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

// $this->route('polling_center')because Laravel converts the route name polling-centers into the singular parameter {polling_center}.
