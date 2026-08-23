<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncUserRolesRequest extends FormRequest
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
        return [
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
            'roles' => [
                'prohibited',
            ],
            'permissions' => [
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
