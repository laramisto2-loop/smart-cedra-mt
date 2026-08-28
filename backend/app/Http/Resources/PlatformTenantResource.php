<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformTenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status,
            'settings' => $this->when(
                $this->relationLoaded('settings'),
                fn (): ?array => $this->settings === null
                    ? null
                    : [
                        'brand_name' => $this->settings->brand_name,
                        'logo_path' => $this->settings->logo_path,
                        'primary_color' => $this->settings->primary_color,
                        'timezone' => $this->settings->timezone,
                    ]
            ),
            'users_count' => $this->users_count ?? 0,
            'roles_count' => $this->roles_count ?? 0,
            'administrator_count' => $this->administrator_count ?? 0,
            'administrators' => UserResource::collection(
                $this->whenLoaded('users')
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
