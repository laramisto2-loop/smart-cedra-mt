<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CallAttemptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'call_assignment_id' => $this->call_assignment_id,
            'performed_by_user_id' => $this->performed_by_user_id,
            'follow_up_task_id' => $this->follow_up_task_id,
            'client_uuid' => $this->client_uuid,
            'reference_code' => $this->reference_code,
            'outcome' => $this->outcome,
            'duration_seconds' => $this->duration_seconds,
            'notes' => $this->notes,
            'attempted_at' => $this->attempted_at?->toISOString(),
            'follow_up_at' => $this->follow_up_at?->toISOString(),
            'performer' => $this->whenLoaded(
                'performer',
                function (): ?array {
                    if ($this->performer === null) {
                        return null;
                    }

                    return [
                        'id' => $this->performer->id,
                        'name' => $this->performer->name,
                        'email' => $this->performer->email,
                    ];
                }
            ),
            'follow_up_task' => new CampaignTaskResource(
                $this->whenLoaded('followUpTask')
            ),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
