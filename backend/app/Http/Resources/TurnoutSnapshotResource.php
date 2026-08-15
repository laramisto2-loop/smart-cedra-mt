<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TurnoutSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $turnoutPercentage = null;

        if (
            $this->registered_voters !== null
            && $this->registered_voters > 0
        ) {
            $turnoutPercentage = round(
                ($this->turnout_count / $this->registered_voters) * 100,
                2
            );
        }

        return [
            'id' => $this->id,
            'client_uuid' => $this->client_uuid,
            'reference_code' => $this->reference_code,
            'reported_by_user_id' => $this->reported_by_user_id,
            'polling_center_id' => $this->polling_center_id,
            'polling_station_id' => $this->polling_station_id,
            'registered_voters' => $this->registered_voters,
            'turnout_count' => $this->turnout_count,
            'turnout_percentage' => $turnoutPercentage,
            'source' => $this->source,
            'notes' => $this->notes,
            'captured_at' => $this->captured_at?->toISOString(),
            'received_at' => $this->received_at?->toISOString(),
            'reporter' => $this->whenLoaded(
                'reporter',
                function (): ?array {
                    if ($this->reporter === null) {
                        return null;
                    }

                    return [
                        'id' => $this->reporter->id,
                        'name' => $this->reporter->name,
                        'email' => $this->reporter->email,
                    ];
                }
            ),
            'polling_center' => new PollingCenterResource(
                $this->whenLoaded('pollingCenter')
            ),
            'polling_station' => new PollingStationResource(
                $this->whenLoaded('pollingStation')
            ),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
