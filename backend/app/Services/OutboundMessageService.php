<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactConsent;
use App\Models\MessageDeliveryEvent;
use App\Models\MessageTemplate;
use App\Models\OutboundMessage;
use App\Models\TenantSetting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OutboundMessageService
{
    private const QUIET_HOURS_START = 21;

    private const QUIET_HOURS_END = 8;

    public function create(
        User $sender,
        array $attributes
    ): OutboundMessage {
        $tenantId = (int) $sender->tenant_id;

        if ($tenantId <= 0) {
            throw ValidationException::withMessages([
                'contact_id' => [
                    'The sender must belong to an active tenant.',
                ],
            ]);
        }

        $clientUuid = filled($attributes['client_uuid'] ?? null)
            ? (string) $attributes['client_uuid']
            : Str::uuid()->toString();

        $existingMessage = OutboundMessage::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('client_uuid', $clientUuid)
            ->first();

        if ($existingMessage !== null) {
            return $this->loadRelations($existingMessage);
        }

        return DB::transaction(function () use (
            $attributes,
            $clientUuid,
            $sender,
            $tenantId
        ): OutboundMessage {
            $contact = Contact::query()
                ->whereKey($attributes['contact_id'])
                ->firstOrFail();

            $template = MessageTemplate::query()
                ->whereKey($attributes['message_template_id'])
                ->where('status', 'approved')
                ->firstOrFail();

            $recipient = trim((string) $contact->phone);

            if ($recipient === '') {
                throw ValidationException::withMessages([
                    'contact_id' => [
                        'The selected contact does not have a phone number.',
                    ],
                ]);
            }

            $variables = $this->resolveVariables(
                $template,
                $contact,
                $attributes['variables'] ?? []
            );

            $renderedBody = $this->renderTemplate(
                $template,
                $variables
            );

            $consent = ContactConsent::query()
                ->where('contact_id', $contact->id)
                ->where('channel', $template->channel)
                ->first();

            $hasGrantedConsent = $consent?->status === 'granted';

            $scheduledAt = $hasGrantedConsent
                ? $this->quietHoursReleaseAt($tenantId)
                : null;

            $status = match (true) {
                ! $hasGrantedConsent => 'suppressed',
                $scheduledAt !== null => 'scheduled',
                default => 'queued',
            };

            $message = OutboundMessage::query()->create([
                'tenant_id' => $tenantId,
                'contact_id' => $contact->id,
                'message_template_id' => $template->id,
                'contact_consent_id' => $consent?->id,
                'sent_by_user_id' => $sender->id,
                'client_uuid' => $clientUuid,
                'channel' => $template->channel,
                'recipient' => $recipient,
                'rendered_body' => $renderedBody,
                'variables' => $variables,
                'source' => 'manual',
                'provider' => $template->provider,
                'status' => $status,
                'consent_status' => $consent?->status ?? 'unknown',
                'consent_checked_at' => now(),
                'suppression_reason' => $this->suppressionReason(
                    $consent,
                    $template
                ),
                'scheduled_at' => $scheduledAt,
                'queued_at' => $status === 'queued'
                    ? now()
                    : null,
            ]);

            if (in_array($status, ['queued', 'scheduled'], true)) {
                MessageDeliveryEvent::query()->create([
                    'tenant_id' => $tenantId,
                    'outbound_message_id' => $message->id,
                    'provider' => $template->provider,
                    'event_type' => $status,
                    'status' => $status,
                    'metadata' => [
                        'source' => 'application',
                    ],
                    'occurred_at' => now(),
                    'received_at' => now(),
                ]);
            }

            return $this->loadRelations($message);
        });
    }

    /**
     * @param  array<string, mixed>  $providedVariables
     * @return array<string, string>
     */
    private function resolveVariables(
        MessageTemplate $template,
        Contact $contact,
        array $providedVariables
    ): array {
        if (array_is_list($providedVariables) && $providedVariables !== []) {
            throw ValidationException::withMessages([
                'variables' => [
                    'Message variables must use variable names as keys.',
                ],
            ]);
        }

        $requiredVariables = array_values(
            array_unique($template->variables ?? [])
        );

        $unknownVariables = array_diff(
            array_keys($providedVariables),
            $requiredVariables
        );

        if ($unknownVariables !== []) {
            throw ValidationException::withMessages([
                'variables' => [
                    'One or more variables are not declared by the template.',
                ],
            ]);
        }

        $automaticVariables = [
            'first_name' => (string) $contact->first_name,
            'last_name' => (string) $contact->last_name,
            'full_name' => trim(
                "{$contact->first_name} {$contact->last_name}"
            ),
            'name_ar' => (string) $contact->name_ar,
            'phone' => (string) $contact->phone,
            'email' => (string) $contact->email,
            'reference_code' => (string) $contact->reference_code,
        ];

        $resolvedVariables = [];
        $errors = [];

        foreach ($requiredVariables as $variable) {
            $value = array_key_exists($variable, $providedVariables)
                ? $providedVariables[$variable]
                : ($automaticVariables[$variable] ?? null);

            if ($value === null || trim((string) $value) === '') {
                $errors["variables.{$variable}"] = [
                    "The {$variable} variable is required.",
                ];

                continue;
            }

            $resolvedVariables[$variable] = trim((string) $value);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $resolvedVariables;
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function renderTemplate(
        MessageTemplate $template,
        array $variables
    ): string {
        $renderedBody = $template->body;

        foreach ($variables as $name => $value) {
            $pattern = '/{{\s*'.preg_quote($name, '/').'\s*}}/u';

            $renderedBody = preg_replace_callback(
                $pattern,
                fn (): string => $value,
                $renderedBody
            ) ?? $renderedBody;
        }

        if (
            preg_match(
                '/{{\s*[a-zA-Z][a-zA-Z0-9_]*\s*}}/u',
                $renderedBody
            ) === 1
        ) {
            throw ValidationException::withMessages([
                'variables' => [
                    'The approved template contains an unresolved variable.',
                ],
            ]);
        }

        return $renderedBody;
    }

    private function quietHoursReleaseAt(
        int $tenantId
    ): ?Carbon {
        $timezone = TenantSetting::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->value('timezone') ?? 'UTC';

        try {
            $localNow = now($timezone);
        } catch (\Throwable) {
            $localNow = now('UTC');
        }

        $hour = (int) $localNow->format('G');

        if ($hour >= self::QUIET_HOURS_START) {
            return $localNow
                ->copy()
                ->addDay()
                ->startOfDay()
                ->addHours(self::QUIET_HOURS_END)
                ->utc();
        }

        if ($hour < self::QUIET_HOURS_END) {
            return $localNow
                ->copy()
                ->startOfDay()
                ->addHours(self::QUIET_HOURS_END)
                ->utc();
        }

        return null;
    }

    private function suppressionReason(
        ?ContactConsent $consent,
        MessageTemplate $template
    ): ?string {
        if ($consent === null) {
            return "No {$template->channel} consent decision exists for this contact.";
        }

        if ($consent->status !== 'granted') {
            return "The contact's {$template->channel} consent status is {$consent->status}.";
        }

        return null;
    }

    private function loadRelations(
        OutboundMessage $message
    ): OutboundMessage {
        return $message->load([
            'contact',
            'template.creator',
            'consent',
            'sender',
            'deliveryEvents' => fn ($query) => $query
                ->orderBy('occurred_at'),
        ]);
    }
}
