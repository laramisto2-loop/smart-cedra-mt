<?php

namespace App\Http\Requests;

use App\Models\ElectionContest;
use App\Models\ElectionOption;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreElectionContestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ElectionContest::class) ?? false;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'code' => [
                'required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('election_contests', 'code')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'election_date' => ['nullable', 'date'],
            'options' => ['required', 'array', 'min:1', 'max:100'],
            'options.*.code' => [
                'required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', 'distinct:strict',
            ],
            'options.*.name' => ['required', 'string', 'max:255'],
            'options.*.option_type' => ['required', Rule::in(ElectionOption::TYPES)],
            'options.*.ballot_order' => ['nullable', 'integer', 'min:1', 'distinct:strict'],
            'options.*.description' => ['nullable', 'string', 'max:5000'],
            'options.*.is_active' => ['sometimes', 'boolean'],
            'tenant_id' => ['prohibited'],
            'created_by_user_id' => ['prohibited'],
            'activated_by_user_id' => ['prohibited'],
            'status' => ['prohibited'],
            'activated_at' => ['prohibited'],
            'closed_at' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $options = collect($this->input('options', []))
            ->map(function ($option) {
                if (is_array($option) && array_key_exists('code', $option)) {
                    $option['code'] = strtoupper(trim((string) $option['code']));
                }

                return $option;
            })
            ->all();

        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'options' => $options,
        ]);
    }
}
