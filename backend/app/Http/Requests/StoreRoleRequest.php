<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Role::class) ?? false;
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
            'slug' => [
                'required',
                'string',
                'lowercase',
                'max:255',
                'regex:/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/',
                Rule::unique('roles', 'slug')->where(
                    fn ($query) => $query->where(
                        'tenant_id',
                        $this->user()?->tenant_id
                    )
                ),
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
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
}
