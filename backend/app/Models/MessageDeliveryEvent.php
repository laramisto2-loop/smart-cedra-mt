<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class MessageDeliveryEvent extends Model
{
    use BelongsToTenant, HasFactory;

    public const EVENT_TYPES = [
        'queued',
        'scheduled',
        'submitted',
        'sent',
        'delivered',
        'read',
        'failed',
        'rejected',
        'undelivered',
        'opt_out',
        'provider_update',
    ];

    protected $fillable = [
        'tenant_id',
        'outbound_message_id',
        'provider',
        'provider_event_id',
        'event_type',
        'status',
        'metadata',
        'occurred_at',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (
            MessageDeliveryEvent $event
        ): void {
            $tenantId = app(TenantContext::class)->id()
                ?? $event->tenant_id;

            if ($tenantId === null) {
                throw new LogicException(
                    'A message delivery event must belong to a tenant.'
                );
            }

            if (
                ! in_array(
                    $event->event_type,
                    self::EVENT_TYPES,
                    true
                )
            ) {
                throw new LogicException(
                    'The message delivery event type is invalid.'
                );
            }

            if (
                $event->status !== null
                && ! in_array(
                    $event->status,
                    OutboundMessage::STATUSES,
                    true
                )
            ) {
                throw new LogicException(
                    'The message delivery event status is invalid.'
                );
            }

            $message = self::findOutboundMessage(
                $event->outbound_message_id,
                (int) $tenantId
            );

            if (
                filled($event->provider)
                && filled($message->provider)
                && $event->provider !== $message->provider
            ) {
                throw new LogicException(
                    'The delivery event provider must match the outbound message provider.'
                );
            }

            if (blank($event->provider)) {
                $event->provider = $message->provider;
            }

            $event->occurred_at ??= now();
            $event->received_at ??= now();
        });

        static::updating(function (): void {
            throw new LogicException(
                'Message delivery events are immutable.'
            );
        });

        static::deleting(function (): void {
            throw new LogicException(
                'Message delivery events cannot be deleted.'
            );
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function outboundMessage(): BelongsTo
    {
        return $this->belongsTo(OutboundMessage::class);
    }

    private static function findOutboundMessage(
        ?int $messageId,
        int $tenantId
    ): OutboundMessage {
        if ($messageId === null) {
            throw new LogicException(
                'A delivery event must belong to an outbound message.'
            );
        }

        $message = OutboundMessage::withoutGlobalScopes()
            ->whereKey($messageId)
            ->first();

        if (
            $message === null
            || (int) $message->tenant_id !== $tenantId
        ) {
            throw new LogicException(
                'The delivery event message must belong to the same tenant.'
            );
        }

        return $message;
    }
}
//This creates an append-only delivery history: provider events may be created, but never edited or deleted