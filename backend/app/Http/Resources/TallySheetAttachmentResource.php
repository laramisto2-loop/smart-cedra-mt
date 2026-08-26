<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TallySheetAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tally_sheet_id' => $this->tally_sheet_id,
            'uploaded_by_user_id' => $this->uploaded_by_user_id,
            'client_uuid' => $this->client_uuid,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'captured_at' => $this->captured_at?->toISOString(),
            'client_updated_at' => $this->client_updated_at?->toISOString(),
            'uploader' => $this->whenLoaded('uploader', fn () => $this->uploader === null ? null : [
                'id' => $this->uploader->id,
                'name' => $this->uploader->name,
                'email' => $this->uploader->email,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
