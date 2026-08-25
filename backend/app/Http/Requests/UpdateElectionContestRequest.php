<?php

namespace App\Http\Requests;

use App\Models\ElectionContest;
use App\Models\ElectionOption;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateElectionContestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contest = $this->route('electionContest');

        return $contest instanceof ElectionContest
            && ($this->user()?->can('update', $contest) ?? false);
    }

    public function rules(): array
    {
        $contest = $this->route('electionContest');
        $tenantId = app(TenantContext::class)->id();

        return [
            'code' => [
                'sometimes', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('election_contests', 'code')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($contest?->id),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'election_date' => ['sometimes', 'nullable', 'date'],
            'options' => ['sometimes', 'array', 'min:1', 'max:100'],
            'options.*.id' => [
                'sometimes', 'integer',
                Rule::exists('election_options', 'id')->where(
                    fn ($query) => $query
                        ->where('tenant_id', $tenantId)
                        ->where('election_contest_id', $contest?->id)
                ),
            ],
            'options.*.code' => [
                'required_with:options', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', 'distinct:strict',
            ],
            'options.*.name' => ['required_with:options', 'string', 'max:255'],
            'options.*.option_type' => ['required_with:options', Rule::in(ElectionOption::TYPES)],
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
        $payload = [];

        if ($this->has('code')) {
            $payload['code'] = strtoupper(trim((string) $this->input('code')));
        }

        if ($this->has('options')) {
            $payload['options'] = collect($this->input('options', []))
                ->map(function ($option) {
                    if (is_array($option) && array_key_exists('code', $option)) {
                        $option['code'] = strtoupper(trim((string) $option['code']));
                    }

                    return $option;
                })
                ->all();
        }

        $this->merge($payload);
    }
}
