<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PollingStationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'polling_center_id' => $this->polling_center_id,
            'station_number' => $this->station_number,
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'room' => $this->room,
            'registered_voters' => $this->registered_voters,
            'polling_center' => new PollingCenterResource(
                $this->whenLoaded('pollingCenter')
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
