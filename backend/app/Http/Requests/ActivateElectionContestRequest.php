<?php

namespace App\Http\Requests;

use App\Models\ElectionContest;
use Illuminate\Foundation\Http\FormRequest;

class ActivateElectionContestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contest = $this->route('electionContest');

        return $contest instanceof ElectionContest
            && ($this->user()?->can('activate', $contest) ?? false);
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['prohibited'],
            'status' => ['prohibited'],
            'activated_by_user_id' => ['prohibited'],
            'activated_at' => ['prohibited'],
            'closed_at' => ['prohibited'],
        ];
    }
}
