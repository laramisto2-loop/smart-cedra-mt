<?php

namespace App\Http\Requests;

use App\Models\TallySheet;
use App\Models\TallySheetAttachment;
use Illuminate\Foundation\Http\FormRequest;

class StoreTallySheetAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sheet = $this->route('tallySheet');

        return $sheet instanceof TallySheet
            && ! $sheet->isFinalized()
            && ($this->user()?->can('create', TallySheetAttachment::class) ?? false)
            && ($this->user()?->can('view', $sheet) ?? false);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:15360'],
            'client_uuid' => ['sometimes', 'nullable', 'uuid'],
            'captured_at' => ['nullable', 'date'],
            'client_updated_at' => ['nullable', 'date'],
            'tenant_id' => ['prohibited'],
            'tally_sheet_id' => ['prohibited'],
            'uploaded_by_user_id' => ['prohibited'],
            'disk' => ['prohibited'],
            'path' => ['prohibited'],
            'original_name' => ['prohibited'],
            'mime_type' => ['prohibited'],
            'size_bytes' => ['prohibited'],
            'checksum_sha256' => ['prohibited'],
        ];
    }
}
