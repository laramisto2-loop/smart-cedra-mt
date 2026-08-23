<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CallAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'call_queue_id' => $this->call_queue_id,
            'contact_id' => $this->contact_id,
            'assigned_to_user_id' => $this->assigned_to_user_id,
            'assigned_by_user_id' => $this->assigned_by_user_id,
            'status' => $this->status,
            'priority' => $this->priority,
            'scheduled_for' => $this->scheduled_for?->toISOString(),
            'claimed_at' => $this->claimed_at?->toISOString(),
            'last_attempted_at' => $this->last_attempted_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'notes' => $this->notes,
            'attempts_count' => $this->whenCounted('attempts'),
            'call_queue' => $this->whenLoaded(
                'callQueue',
                function (): ?array {
                    if ($this->callQueue === null) {
                        return null;
                    }

                    return [
                        'id' => $this->callQueue->id,
                        'name' => $this->callQueue->name,
                        'code' => $this->callQueue->code,
                        'priority' => $this->callQueue->priority,
                        'status' => $this->callQueue->status,
                        'call_script' => (
                            $this->callQueue->relationLoaded('callScript')
                            && $this->callQueue->callScript !== null
                        )
                            ? [
                                'id' => $this->callQueue->callScript->id,
                                'name' => $this->callQueue->callScript->name,
                                'code' => $this->callQueue->callScript->code,
                                'language_code' => $this->callQueue->callScript->language_code,
                                'body' => $this->callQueue->callScript->body,
                                'status' => $this->callQueue->callScript->status,
                            ]
                            : null,
                    ];
                }
            ),
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
                        'preferred_channel' => $this->contact
                            ->preferred_channel,
                    ];
                }
            ),
            'assignee' => $this->whenLoaded(
                'assignee',
                function (): ?array {
                    if ($this->assignee === null) {
                        return null;
                    }

                    return [
                        'id' => $this->assignee->id,
                        'name' => $this->assignee->name,
                        'email' => $this->assignee->email,
                    ];
                }
            ),
            'assigner' => $this->whenLoaded(
                'assigner',
                function (): ?array {
                    if ($this->assigner === null) {
                        return null;
                    }

                    return [
                        'id' => $this->assigner->id,
                        'name' => $this->assigner->name,
                        'email' => $this->assigner->email,
                    ];
                }
            ),
            'attempts' => CallAttemptResource::collection(
                $this->whenLoaded('attempts')
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
