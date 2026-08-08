<?php

namespace App\Http\Requests;

use App\Models\Contact;
use App\Models\ContactConsent;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contact = $this->route('contact');

        return $contact instanceof Contact
            && ($this->user()?->can('update', $contact) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $contact = $this->route('contact');

        return [
            'tenant_id' => [
                'prohibited',
            ],
            'created_by_user_id' => [
                'prohibited',
            ],
            'reference_code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('contacts', 'reference_code')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $tenantId
                        )
                    )
                    ->ignore(
                        $contact instanceof Contact
                            ? $contact->id
                            : null
                    ),
            ],
            'area_id' => [
                'sometimes',
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
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'last_name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'name_ar' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
            ],
            'address' => [
                'sometimes',
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
                'sometimes',
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
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],
            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}
