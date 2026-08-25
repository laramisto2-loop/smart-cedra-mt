<?php

namespace App\Http\Requests;

use App\Models\TallySheet;
use Illuminate\Foundation\Http\FormRequest;

class RejectTallySheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sheet = $this->route('tallySheet');

        return $sheet instanceof TallySheet
            && ($this->user()?->can('reject', $sheet) ?? false);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:5000'],
            'tenant_id' => ['prohibited'],
            'status' => ['prohibited'],
            'approved_by_user_id' => ['prohibited'],
            'rejected_at' => ['prohibited'],
        ];
    }
}
