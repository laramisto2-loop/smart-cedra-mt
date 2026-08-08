<?php

namespace App\Http\Requests;

use App\Models\Contact;
use App\Models\ContactConsent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordContactConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contact = $this->route('contact');

        return $contact instanceof Contact
            && ($this->user()?->can(
                'manageConsent',
                $contact
            ) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => [
                'prohibited',
            ],
            'contact_id' => [
                'prohibited',
            ],
            'recorded_by_user_id' => [
                'prohibited',
            ],
            'consented_at' => [
                'prohibited',
            ],
            'revoked_at' => [
                'prohibited',
            ],
            'channel' => [
                'required',
                'string',
                Rule::in(ContactConsent::CHANNELS),
            ],
            'status' => [
                'required',
                'string',
                Rule::in(ContactConsent::STATUSES),
            ],
            'source' => [
                'required',
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
// consent recording is now server-controlled and authorization-safe.
