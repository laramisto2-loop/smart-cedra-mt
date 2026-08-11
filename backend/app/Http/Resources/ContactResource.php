<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'area_id' => $this->area_id,
            'reference_code' => $this->reference_code,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => trim(
                "{$this->first_name} {$this->last_name}"
            ),
            'name_ar' => $this->name_ar,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'preferred_language' => $this->preferred_language,
            'preferred_channel' => $this->preferred_channel,
            'status' => $this->status,
            'source' => $this->source,
            'notes' => $this->notes,
            'area' => new AreaResource(
                $this->whenLoaded('area')
            ),
            'creator' => $this->whenLoaded(
                'creator',
                function (): ?array {
                    if ($this->creator === null) {
                        return null;
                    }

                    return [
                        'id' => $this->creator->id,
                        'name' => $this->creator->name,
                    ];
                }
            ),
            'consents' => $this->whenLoaded(
                'consents',
                fn () => $this->consents
                    ->map(fn ($consent): array => [
                        'id' => $consent->id,
                        'channel' => $consent->channel,
                        'status' => $consent->status,
                        'source' => $consent->source,
                        'consented_at' => $consent
                            ->consented_at?->toISOString(),
                        'revoked_at' => $consent
                            ->revoked_at?->toISOString(),
                        'notes' => $consent->notes,
                        'recorded_by' => $consent->recorder === null
                            ? null
                            : [
                                'id' => $consent->recorder->id,
                                'name' => $consent->recorder->name,
                            ],
                        'updated_at' => $consent
                            ->updated_at?->toISOString(),
                    ])
                    ->values()
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
