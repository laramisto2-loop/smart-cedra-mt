<?php

namespace App\Http\Requests;

use App\Models\Contact;
use App\Models\ContactConsent;
use App\Models\Segment;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSegmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            Segment::class
        ) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge([
                'code' => strtoupper(
                    trim((string) $this->input('code'))
                ),
            ]);
        }
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
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9][A-Z0-9_-]*$/',
                Rule::unique('segments', 'code')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $tenantId
                        )
                    ),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'type' => [
                'sometimes',
                'required',
                'string',
                Rule::in(Segment::TYPES),
            ],
            'criteria' => [
                'prohibited_unless:type,dynamic',
                'required_if:type,dynamic',
                'array:contact_status,area_id,preferred_language,preferred_channel,consent_channel,consent_status',
            ],
            'criteria.contact_status' => [
                'sometimes',
                'required',
                'string',
                Rule::in(Contact::STATUSES),
            ],
            'criteria.area_id' => [
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
            'criteria.preferred_language' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    'en',
                    'ar',
                ]),
            ],
            'criteria.preferred_channel' => [
                'sometimes',
                'required',
                'string',
                Rule::in(ContactConsent::CHANNELS),
            ],
            'criteria.consent_channel' => [
                'sometimes',
                'required',
                'string',
                Rule::in(ContactConsent::CHANNELS),
            ],
            'criteria.consent_status' => [
                'sometimes',
                'required',
                'string',
                Rule::in(ContactConsent::STATUSES),
            ],
            'status' => [
                'sometimes',
                'required',
                'string',
                Rule::in(Segment::STATUSES),
            ],
        ];
    }
}
