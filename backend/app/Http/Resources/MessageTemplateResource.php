<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageTemplateResource extends JsonResource
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
            'channel' => $this->channel,
            'provider' => $this->provider,
            'provider_template_name' => $this->provider_template_name,
            'language_code' => $this->language_code,
            'category' => $this->category,
            'body' => $this->body,
            'variables' => $this->variables ?? [],
            'status' => $this->status,
            'approved_at' => $this->approved_at?->toISOString(),
            'outbound_messages_count' => $this->whenCounted(
                'outboundMessages'
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
                        'email' => $this->creator->email,
                    ];
                }
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
