<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CallQueueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'call_script_id' => $this->call_script_id,
            'created_by_user_id' => $this->created_by_user_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'assignments_count' => $this->whenCounted(
                'assignments'
            ),
            'call_script' => new CallScriptResource(
                $this->whenLoaded('callScript')
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
            'assignments' => CallAssignmentResource::collection(
                $this->whenLoaded('assignments')
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
