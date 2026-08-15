<?php

namespace App\Http\Requests;

use App\Models\OutboundMessage;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOutboundMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            OutboundMessage::class
        ) ?? false;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'contact_id' => [
                'required',
                'integer',
                Rule::exists('contacts', 'id')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $tenantId
                        )
                    ),
            ],
            'message_template_id' => [
                'required',
                'integer',
                Rule::exists('message_templates', 'id')
                    ->where(
                        fn ($query) => $query
                            ->where('tenant_id', $tenantId)
                            ->where('status', 'approved')
                    ),
            ],
            'client_uuid' => [
                'sometimes',
                'nullable',
                'uuid',
            ],
            'variables' => [
                'nullable',
                'array',
                'max:50',
            ],
            'variables.*' => [
                'nullable',
                'string',
                'max:1000',
            ],

            // Identity, consent, content, provider, and workflow fields
            // are derived and controlled by the server.
            'tenant_id' => [
                'prohibited',
            ],
            'contact_consent_id' => [
                'prohibited',
            ],
            'sent_by_user_id' => [
                'prohibited',
            ],
            'reference_code' => [
                'prohibited',
            ],
            'channel' => [
                'prohibited',
            ],
            'recipient' => [
                'prohibited',
            ],
            'template_code' => [
                'prohibited',
            ],
            'rendered_body' => [
                'prohibited',
            ],
            'source' => [
                'prohibited',
            ],
            'provider' => [
                'prohibited',
            ],
            'provider_message_id' => [
                'prohibited',
            ],
            'status' => [
                'prohibited',
            ],
            'consent_status' => [
                'prohibited',
            ],
            'consent_checked_at' => [
                'prohibited',
            ],
            'suppression_reason' => [
                'prohibited',
            ],
            'error_code' => [
                'prohibited',
            ],
            'error_message' => [
                'prohibited',
            ],
            'scheduled_at' => [
                'prohibited',
            ],
            'queued_at' => [
                'prohibited',
            ],
            'sent_at' => [
                'prohibited',
            ],
            'delivered_at' => [
                'prohibited',
            ],
            'read_at' => [
                'prohibited',
            ],
            'failed_at' => [
                'prohibited',
            ],
        ];
    }
}
