<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OutboundMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contact_id' => $this->contact_id,
            'message_template_id' => $this->message_template_id,
            'contact_consent_id' => $this->contact_consent_id,
            'sent_by_user_id' => $this->sent_by_user_id,
            'client_uuid' => $this->client_uuid,
            'reference_code' => $this->reference_code,
            'channel' => $this->channel,
            'recipient' => $this->recipient,
            'template_code' => $this->template_code,
            'rendered_body' => $this->rendered_body,
            'variables' => $this->variables ?? [],
            'source' => $this->source,
            'provider' => $this->provider,
            'provider_message_id' => $this->provider_message_id,
            'status' => $this->status,
            'consent_status' => $this->consent_status,
            'consent_checked_at' => $this->consent_checked_at?->toISOString(),
            'suppression_reason' => $this->suppression_reason,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'queued_at' => $this->queued_at?->toISOString(),
            'sent_at' => $this->sent_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'read_at' => $this->read_at?->toISOString(),
            'failed_at' => $this->failed_at?->toISOString(),
            'contact' => $this->whenLoaded(
                'contact',
                function (): ?array {
                    if ($this->contact === null) {
                        return null;
                    }

                    return [
                        'id' => $this->contact->id,
                        'reference_code' => $this->contact->reference_code,
                        'full_name' => trim(
                            "{$this->contact->first_name} {$this->contact->last_name}"
                        ),
                        'phone' => $this->contact->phone,
                    ];
                }
            ),
            'template' => new MessageTemplateResource(
                $this->whenLoaded('template')
            ),
            'consent' => $this->whenLoaded(
                'consent',
                function (): ?array {
                    if ($this->consent === null) {
                        return null;
                    }

                    return [
                        'id' => $this->consent->id,
                        'channel' => $this->consent->channel,
                        'status' => $this->consent->status,
                        'source' => $this->consent->source,
                        'consented_at' => $this->consent
                            ->consented_at?->toISOString(),
                        'revoked_at' => $this->consent
                            ->revoked_at?->toISOString(),
                    ];
                }
            ),
            'sender' => $this->whenLoaded(
                'sender',
                function (): ?array {
                    if ($this->sender === null) {
                        return null;
                    }

                    return [
                        'id' => $this->sender->id,
                        'name' => $this->sender->name,
                        'email' => $this->sender->email,
                    ];
                }
            ),
            'delivery_events' => MessageDeliveryEventResource::collection(
                $this->whenLoaded('deliveryEvents')
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
