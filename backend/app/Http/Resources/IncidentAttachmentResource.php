<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncidentAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'incident_id' => $this->incident_id,
            'client_uuid' => $this->client_uuid,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'captured_at' => $this->captured_at?->toISOString(),
            'client_updated_at' => $this->client_updated_at?->toISOString(),
            'download_url' => route(
                'incident-attachments.download',
                ['incidentAttachment' => $this->id]
            ),
            'uploader' => $this->whenLoaded(
                'uploader',
                function (): ?array {
                    if ($this->uploader === null) {
                        return null;
                    }

                    return [
                        'id' => $this->uploader->id,
                        'name' => $this->uploader->name,
                    ];
                }
            ),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
