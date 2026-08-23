<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $targetUser = $this->targetUser();

        return $targetUser !== null
            && ($this->user()?->can('update', $targetUser) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $targetUser = $this->targetUser();

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'sometimes',
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->ignore($targetUser?->id),
            ],
            'password' => [
                'sometimes',
                'required',
                'confirmed',
                Password::defaults(),
            ],
            'tenant_id' => [
                'prohibited',
            ],
            'role_ids' => [
                'prohibited',
            ],
            'roles' => [
                'prohibited',
            ],
            'permissions' => [
                'prohibited',
            ],
            'email_verified_at' => [
                'prohibited',
            ],
        ];
    }

    private function targetUser(): ?User
    {
        $targetUser = $this->route('user');

        return $targetUser instanceof User
            ? $targetUser
            : null;
    }
}
