<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SegmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'criteria' => $this->criteria,
            'status' => $this->status,
            'member_count' => $this->whenCounted('contacts'),
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
            'members' => ContactResource::collection(
                $this->whenLoaded('contacts')
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
