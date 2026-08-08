<?php

namespace App\Http\Requests;

use App\Models\Contact;
use App\Models\ContactConsent;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            Contact::class
        ) ?? false;
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
            'created_by_user_id' => [
                'prohibited',
            ],
            'reference_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('contacts', 'reference_code')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $tenantId
                        )
                    ),
            ],
            'area_id' => [
                'nullable',
                'integer',
                Rule::exists('areas', 'id')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $tenantId
                        )
                    ),
            ],
            'first_name' => [
                'required',
                'string',
                'max:255',
            ],
            'last_name' => [
                'required',
                'string',
                'max:255',
            ],
            'name_ar' => [
                'nullable',
                'string',
                'max:255',
            ],
            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
            ],
            'address' => [
                'nullable',
                'string',
                'max:255',
            ],
            'preferred_language' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    'en',
                    'ar',
                ]),
            ],
            'preferred_channel' => [
                'nullable',
                'string',
                Rule::in(ContactConsent::CHANNELS),
            ],
            'status' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    'active',
                    'inactive',
                    'archived',
                ]),
            ],
            'source' => [
                'nullable',
                'string',
                'max:50',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}

// Notice that neither tenant_id nor created_by_user_id can be submitted by the browser—the server will assign them securely
