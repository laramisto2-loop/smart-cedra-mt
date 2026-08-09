<?php

namespace App\Http\Requests;

use App\Models\Contact;
use App\Models\ContactInteraction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactInteractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contact = $this->route('contact');
        $user = $this->user();

        return $contact instanceof Contact
            && $user !== null
            && $user->can('view', $contact)
            && $user->can('create', ContactInteraction::class);
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
            'consent_status_snapshot' => [
                'prohibited',
            ],
            'consent_checked_at' => [
                'prohibited',
            ],
            'channel' => [
                'required',
                'string',
                Rule::in(ContactInteraction::CHANNELS),
            ],
            'direction' => [
                'sometimes',
                'required',
                'string',
                Rule::in(ContactInteraction::DIRECTIONS),
            ],
            'outcome' => [
                'nullable',
                'string',
                Rule::in(ContactInteraction::OUTCOMES),
            ],
            'subject' => [
                'nullable',
                'string',
                'max:255',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'duration_seconds' => [
                'nullable',
                'integer',
                'min:0',
                'max:86400',
            ],
            'occurred_at' => [
                'required',
                'date',
                'before_or_equal:now',
            ],
        ];
    }
}
