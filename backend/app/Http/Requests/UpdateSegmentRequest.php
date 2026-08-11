<?php

namespace App\Http\Requests;

use App\Models\Contact;
use App\Models\ContactConsent;
use App\Models\Segment;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSegmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $segment = $this->route('segment');

        return $segment instanceof Segment
            && ($this->user()?->can('update', $segment) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $segment = $this->route('segment');
        $prepared = [];

        if ($this->has('code')) {
            $prepared['code'] = strtoupper(
                trim((string) $this->input('code'))
            );
        }

        if (
            $segment instanceof Segment
            && ! $this->has('type')
        ) {
            $prepared['type'] = $segment->type;
        }

        $effectiveType = $prepared['type']
            ?? $this->input('type');

        if (
            $segment instanceof Segment
            && $effectiveType === 'dynamic'
            && ! $this->has('criteria')
            && $segment->type === 'dynamic'
        ) {
            $prepared['criteria'] = $segment->criteria;
        }

        if ($prepared !== []) {
            $this->merge($prepared);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $segment = $this->route('segment');

        return [
            'tenant_id' => [
                'prohibited',
            ],
            'created_by_user_id' => [
                'prohibited',
            ],
            'code' => [
                'sometimes',
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
                    )
                    ->ignore(
                        $segment instanceof Segment
                            ? $segment->id
                            : null
                    ),
            ],
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],
            'type' => [
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
