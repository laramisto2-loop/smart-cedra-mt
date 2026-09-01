<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'brand_name' => $this->brand_name,
            'primary_color' => $this->primary_color,
            'timezone' => $this->timezone,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
