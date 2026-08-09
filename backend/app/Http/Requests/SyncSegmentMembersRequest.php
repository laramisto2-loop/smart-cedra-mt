<?php

namespace App\Http\Requests;

use App\Models\Segment;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncSegmentMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $segment = $this->route('segment');

        return $segment instanceof Segment
            && ($this->user()?->can(
                'manageMembers',
                $segment
            ) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'tenant_id' => [
                'prohibited',
            ],
            'added_by_user_id' => [
                'prohibited',
            ],
            'contact_ids' => [
                'present',
                'array',
                'max:1000',
            ],
            'contact_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('contacts', 'id')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $tenantId
                        )
                    ),
            ],
        ];
    }
}
