<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contact_id' => $this->contact_id,
            'area_id' => $this->area_id,
            'assigned_to_user_id' => $this->assigned_to_user_id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'priority' => $this->priority,
            'status' => $this->status,
            'due_at' => $this->due_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'completion_notes' => $this->completion_notes,
            'is_overdue' => $this->due_at !== null
                && ! in_array(
                    $this->status,
                    ['completed', 'cancelled'],
                    true
                )
                && $this->due_at->isPast(),
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
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
