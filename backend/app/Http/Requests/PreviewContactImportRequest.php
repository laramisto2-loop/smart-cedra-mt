<?php

namespace App\Http\Requests;

use App\Models\Contact;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PreviewContactImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'import',
            Contact::class
        ) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => [
                'prohibited',
            ],
            'file' => [
                'required',
                'file',
                'max:10240',
                'extensions:csv',
                'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel',
            ],
        ];
    }
}
