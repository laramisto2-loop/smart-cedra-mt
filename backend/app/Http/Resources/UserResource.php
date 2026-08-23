<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_current_user' => $request->user()?->is($this->resource)
                ?? false,
            'roles' => RoleResource::collection(
                $this->whenLoaded('roles')
            ),
            'permissions' => $this->whenLoaded(
                'roles',
                fn () => $this->roles
                    ->flatMap(
                        fn ($role) => $role->permissions->pluck('slug')
                    )
                    ->unique()
                    ->sort()
                    ->values()
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
