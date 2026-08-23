<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncRolePermissionsRequest extends FormRequest
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
        return [
            'permission_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'permission_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('permissions', 'id'),
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
