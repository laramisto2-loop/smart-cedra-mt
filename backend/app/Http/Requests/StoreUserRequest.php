<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'password' => [
                'required',
                'confirmed',
                Password::defaults(),
            ],
            'role_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'role_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('roles', 'id')->where(
                    fn ($query) => $query->where(
                        'tenant_id',
                        $this->user()?->tenant_id
                    )
                ),
            ],
            'tenant_id' => [
                'prohibited',
            ],
            'email_verified_at' => [
                'prohibited',
            ],
            'roles' => [
                'prohibited',
            ],
            'permissions' => [
                'prohibited',
            ],
        ];
    }
}
