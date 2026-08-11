<?php

namespace App\Http\Requests;

use App\Models\ContactInteraction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContactInteractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $interaction = $this->route('contactInteraction');

        return $interaction instanceof ContactInteraction
            && ($this->user()?->can(
                'update',
                $interaction
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
            'consent_status_snapshot' => [
                'prohibited',
            ],
            'consent_checked_at' => [
                'prohibited',
            ],
            'channel' => [
                'sometimes',
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
                'sometimes',
                'nullable',
                'string',
                Rule::in(ContactInteraction::OUTCOMES),
            ],
            'subject' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],
            'duration_seconds' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'max:86400',
            ],
            'occurred_at' => [
                'sometimes',
                'required',
                'date',
                'before_or_equal:now',
            ],
        ];
    }
}
