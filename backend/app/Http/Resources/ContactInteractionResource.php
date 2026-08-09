<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactInteractionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contact_id' => $this->contact_id,
            'channel' => $this->channel,
            'direction' => $this->direction,
            'outcome' => $this->outcome,
            'subject' => $this->subject,
            'notes' => $this->notes,
            'duration_seconds' => $this->duration_seconds,
            'occurred_at' => $this->occurred_at?->toISOString(),
            'consent_status_snapshot' => $this->consent_status_snapshot,
            'consent_checked_at' => $this
                ->consent_checked_at?->toISOString(),
            'recorded_by' => $this->whenLoaded(
                'recorder',
                function (): ?array {
                    if ($this->recorder === null) {
                        return null;
                    }

                    return [
                        'id' => $this->recorder->id,
                        'name' => $this->recorder->name,
                    ];
                }
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
