<?php

namespace App\Http\Requests;

use App\Models\Incident;
use Illuminate\Foundation\Http\FormRequest;

class StoreIncidentAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $incident = $this->route('incident');

        return $incident instanceof Incident
            && ($this->user()?->can(
                'manageAttachments',
                $incident
            ) ?? false);
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:10240',
            ],
            'client_uuid' => [
                'sometimes',
                'nullable',
                'uuid',
            ],
            'captured_at' => [
                'nullable',
                'date',
            ],
            'client_updated_at' => [
                'nullable',
                'date',
            ],

            // Storage and ownership metadata are server-controlled.
            'tenant_id' => [
                'prohibited',
            ],
            'incident_id' => [
                'prohibited',
            ],
            'uploaded_by_user_id' => [
                'prohibited',
            ],
            'disk' => [
                'prohibited',
            ],
            'path' => [
                'prohibited',
            ],
            'original_name' => [
                'prohibited',
            ],
            'mime_type' => [
                'prohibited',
            ],
            'size_bytes' => [
                'prohibited',
            ],
            'checksum_sha256' => [
                'prohibited',
            ],
        ];
    }
}

// This permits JPG, PNG, WebP, and PDF evidence up to 10 MB. Raw storage paths, checksums, ownership, and MIME metadata cannot be forged by the client
