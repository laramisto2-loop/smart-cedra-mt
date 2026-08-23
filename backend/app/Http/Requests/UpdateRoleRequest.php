<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->targetRole();

        return $role !== null
            && ($this->user()?->can('update', $role) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $role = $this->targetRole();

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'lowercase',
                'max:255',
                'regex:/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/',
                Rule::unique('roles', 'slug')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $this->user()?->tenant_id
                        )
                    )
                    ->ignore($role?->id),
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],
            'permission_ids' => [
                'prohibited',
            ],
            'tenant_id' => [
                'prohibited',
            ],
            'permissions' => [
                'prohibited',
            ],
            'users' => [
                'prohibited',
            ],
        ];
    }

    private function targetRole(): ?Role
    {
        $role = $this->route('role');

        return $role instanceof Role
            ? $role
            : null;
    }
}
