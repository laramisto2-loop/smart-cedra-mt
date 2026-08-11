<?php

namespace App\Http\Requests;

class ImportContactRequest extends PreviewContactImportRequest
{
    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['confirmed'] = [
            'required',
            'accepted',
        ];

        return $rules;
    }
}
