<?php

namespace App\Http\Requests;

use App\Models\MessageTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $messageTemplate = $this->route('messageTemplate');

        return $messageTemplate instanceof MessageTemplate
            && ($this->user()?->can(
                'approve',
                $messageTemplate
            ) ?? false);
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    'approved',
                    'rejected',
                ]),
            ],

            // The review endpoint controls only approval status.
            'tenant_id' => [
                'prohibited',
            ],
            'created_by_user_id' => [
                'prohibited',
            ],
            'approved_at' => [
                'prohibited',
            ],
        ];
    }
}
