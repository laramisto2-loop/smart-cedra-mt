<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class OutboundMessage extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    public const CHANNELS = [
        'sms',
        'whatsapp',
    ];

    public const SOURCES = [
        'manual',
        'campaign',
        'automation',
        'call_center',
    ];

    public const STATUSES = [
        'queued',
        'scheduled',
        'sent',
        'delivered',
        'read',
        'failed',
        'suppressed',
        'cancelled',
    ];

    protected $fillable = [
        'tenant_id',
        'contact_id',
        'message_template_id',
        'contact_consent_id',
        'sent_by_user_id',
        'client_uuid',
        'reference_code',
        'channel',
        'recipient',
        'template_code',
        'rendered_body',
        'variables',
        'source',
        'provider',
        'provider_message_id',
        'status',
        'consent_status',
        'consent_checked_at',
        'suppression_reason',
        'error_code',
        'error_message',
        'scheduled_at',
        'queued_at',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'consent_checked_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (OutboundMessage $message): void {
            $tenantId = app(TenantContext::class)->id()
                ?? $message->tenant_id;

            if ($tenantId === null) {
                throw new LogicException(
                    'An outbound message must belong to a tenant.'
                );
            }

            if (
                ! in_array(
                    $message->channel,
                    self::CHANNELS,
                    true
                )
            ) {
                throw new LogicException(
                    'The outbound message channel is invalid.'
                );
            }

            if (
                ! in_array(
                    $message->source,
                    self::SOURCES,
                    true
                )
            ) {
                throw new LogicException(
                    'The outbound message source is invalid.'
                );
            }

            if (
                ! in_array(
                    $message->status,
                    self::STATUSES,
                    true
                )
            ) {
                throw new LogicException(
                    'The outbound message status is invalid.'
                );
            }

            if (blank($message->client_uuid)) {
                $message->client_uuid = Str::uuid()->toString();
            }

            if (blank($message->reference_code)) {
                $compactUuid = str_replace(
                    '-',
                    '',
                    (string) $message->client_uuid
                );

                $message->reference_code = 'MSG-'.strtoupper(
                    substr($compactUuid, 0, 12)
                );
            }

            if (blank($message->recipient)) {
                throw new LogicException(
                    'An outbound message recipient is required.'
                );
            }

            if (blank($message->rendered_body)) {
                throw new LogicException(
                    'An outbound message body is required.'
                );
            }

            self::ensureContactBelongsToTenant(
                $message->contact_id,
                (int) $tenantId
            );

            self::ensureSenderBelongsToTenant(
                $message->sent_by_user_id,
                (int) $tenantId
            );

            self::ensureTemplateIsValid(
                $message,
                (int) $tenantId
            );

            self::applyAndValidateConsent(
                $message,
                (int) $tenantId
            );

            self::applyStatusTimestamp($message);
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(
            MessageTemplate::class,
            'message_template_id'
        );
    }

    public function consent(): BelongsTo
    {
        return $this->belongsTo(
            ContactConsent::class,
            'contact_consent_id'
        );
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'sent_by_user_id'
        );
    }

    public function deliveryEvents(): HasMany
    {
        return $this->hasMany(MessageDeliveryEvent::class);
    }

    private static function ensureContactBelongsToTenant(
        ?int $contactId,
        int $tenantId
    ): void {
        if ($contactId === null) {
            throw new LogicException(
                'An outbound message must belong to a contact.'
            );
        }

        $contactTenantId = Contact::withoutGlobalScopes()
            ->whereKey($contactId)
            ->value('tenant_id');

        if ((int) $contactTenantId !== $tenantId) {
            throw new LogicException(
                'The outbound message contact must belong to the same tenant.'
            );
        }
    }

    private static function ensureSenderBelongsToTenant(
        ?int $userId,
        int $tenantId
    ): void {
        if ($userId === null) {
            return;
        }

        $userTenantId = User::query()
            ->whereKey($userId)
            ->value('tenant_id');

        if ((int) $userTenantId !== $tenantId) {
            throw new LogicException(
                'The outbound message sender must belong to the same tenant.'
            );
        }
    }

    private static function ensureTemplateIsValid(
        OutboundMessage $message,
        int $tenantId
    ): void {
        if ($message->message_template_id === null) {
            return;
        }

        $template = MessageTemplate::withoutGlobalScopes()
            ->whereKey($message->message_template_id)
            ->first([
                'tenant_id',
                'code',
                'channel',
                'status',
            ]);

        if (
            $template === null
            || (int) $template->tenant_id !== $tenantId
        ) {
            throw new LogicException(
                'The outbound message template must belong to the same tenant.'
            );
        }

        if ($template->channel !== $message->channel) {
            throw new LogicException(
                'The outbound message channel must match its template channel.'
            );
        }

        if (
            $message->status !== 'suppressed'
            && $template->status !== 'approved'
        ) {
            throw new LogicException(
                'Only approved templates may be used for outbound messages.'
            );
        }

        $message->template_code = $template->code;
    }

    private static function applyAndValidateConsent(
        OutboundMessage $message,
        int $tenantId
    ): void {
        $message->consent_checked_at ??= now();

        if ($message->contact_consent_id === null) {
            $message->consent_status = 'unknown';

            if ($message->status !== 'suppressed') {
                throw new LogicException(
                    'Granted consent is required before an outbound message may be queued.'
                );
            }

            return;
        }

        $consent = ContactConsent::withoutGlobalScopes()
            ->whereKey($message->contact_consent_id)
            ->first([
                'tenant_id',
                'contact_id',
                'channel',
                'status',
            ]);

        if (
            $consent === null
            || (int) $consent->tenant_id !== $tenantId
        ) {
            throw new LogicException(
                'The outbound message consent must belong to the same tenant.'
            );
        }

        if (
            (int) $consent->contact_id
            !== (int) $message->contact_id
        ) {
            throw new LogicException(
                'The outbound message consent must belong to the selected contact.'
            );
        }

        if ($consent->channel !== $message->channel) {
            throw new LogicException(
                'The outbound message consent channel must match the message channel.'
            );
        }

        $message->consent_status = $consent->status;

        if (
            $message->status !== 'suppressed'
            && $consent->status !== 'granted'
        ) {
            throw new LogicException(
                'Granted consent is required before an outbound message may be queued.'
            );
        }
    }

    private static function applyStatusTimestamp(
        OutboundMessage $message
    ): void {
        $timestampField = match ($message->status) {
            'queued' => 'queued_at',
            'sent' => 'sent_at',
            'delivered' => 'delivered_at',
            'read' => 'read_at',
            'failed' => 'failed_at',
            default => null,
        };

        if (
            $timestampField !== null
            && $message->{$timestampField} === null
        ) {
            $message->{$timestampField} = now();
        }
    }
}
// This is the key consent-protection layer: an SMS or WhatsApp message cannot be queued unless the matching contact has granted consent for that exact channel. Suppressed attempts can still be retained as an audit record
