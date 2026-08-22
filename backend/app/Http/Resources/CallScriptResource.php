<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CallScriptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_by_user_id' => $this->created_by_user_id,
            'name' => $this->name,
            'code' => $this->code,
            'language_code' => $this->language_code,
            'description' => $this->description,
            'body' => $this->body,
            'status' => $this->status,
            'activated_at' => $this->activated_at?->toISOString(),
            'queues_count' => $this->whenCounted('queues'),
            'creator' => $this->whenLoaded(
                'creator',
                function (): ?array {
                    if ($this->creator === null) {
                        return null;
                    }

                    return [
                        'id' => $this->creator->id,
                        'name' => $this->creator->name,
                        'email' => $this->creator->email,
                    ];
                }
            ),
            'queues' => CallQueueResource::collection(
                $this->whenLoaded('queues')
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
