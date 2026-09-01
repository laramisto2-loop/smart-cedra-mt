<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthenticatedUserResource extends JsonResource
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
            'is_platform_admin' => $this->isPlatformAdministrator(),
            'tenant' => $this->whenLoaded(
                'tenant',
                function (): ?array {
                    if ($this->tenant === null) {
                        return null;
                    }

                    return [
                        'id' => $this->tenant->id,
                        'name' => $this->tenant->name,
                        'slug' => $this->tenant->slug,
                        'status' => $this->tenant->status,
                        'settings' => $this->tenant->relationLoaded('settings')
                            && $this->tenant->settings !== null
                            ? [
                                'brand_name' => $this->tenant->settings->brand_name,
                                'primary_color' => $this->tenant->settings->primary_color,
                                'timezone' => $this->tenant->settings->timezone,
                            ]
                            : null,
                    ];
                }
            ),
            'roles' => $this->whenLoaded(
                'roles',
                fn () => $this->roles
                    ->map(fn ($role): array => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'slug' => $role->slug,
                    ])
                    ->values()
            ),
            'permissions' => $this->whenLoaded(
                'roles',
                fn () => $this->roles
                    ->flatMap(
                        fn ($role) => $role->permissions
                            ->pluck('slug')
                    )
                    ->unique()
                    ->sort()
                    ->values()
            ),
        ];
    }
}
