<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageDeliveryEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'outbound_message_id' => $this->outbound_message_id,
            'provider' => $this->provider,
            'provider_event_id' => $this->provider_event_id,
            'event_type' => $this->event_type,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'occurred_at' => $this->occurred_at?->toISOString(),
            'received_at' => $this->received_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
