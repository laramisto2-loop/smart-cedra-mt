<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PollingCenterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'area_id' => $this->area_id,
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'code' => $this->code,
            'address_en' => $this->address_en,
            'address_ar' => $this->address_ar,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'area' => new AreaResource(
                $this->whenLoaded('area')
            ),
            'polling_stations_count' => $this->whenCounted(
                'pollingStations'
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
