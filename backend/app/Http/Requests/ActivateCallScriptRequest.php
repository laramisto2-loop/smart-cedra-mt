<?php

namespace App\Http\Requests;

use App\Models\CallScript;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivateCallScriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $callScript = $this->route('callScript');

        return $callScript instanceof CallScript
            && ($this->user()?->can(
                'activate',
                $callScript
            ) ?? false);
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    'active',
                    'archived',
                ]),
            ],

            // This endpoint controls only the script lifecycle.
            'tenant_id' => [
                'prohibited',
            ],
            'created_by_user_id' => [
                'prohibited',
            ],
            'activated_at' => [
                'prohibited',
            ],
        ];
    }
}
