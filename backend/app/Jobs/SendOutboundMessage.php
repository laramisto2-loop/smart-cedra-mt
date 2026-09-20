<?php

namespace App\Jobs;

use App\Models\MessageDeliveryEvent;
use App\Models\MessageTemplate;
use App\Models\OutboundMessage;
use App\Services\InfobipWhatsAppClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SendOutboundMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $outboundMessageId
    ) {
    }

    public function handle(
        InfobipWhatsAppClient $client
    ): void {
        $message = OutboundMessage::withoutGlobalScopes()
            ->findOrFail($this->outboundMessageId);

        if (
            ! in_array(
                $message->status,
                ['queued', 'scheduled'],
                true
            )
        ) {
            return;
        }

        if ($message->channel !== 'whatsapp') {
            throw new RuntimeException(
                'This delivery job currently supports WhatsApp only.'
            );
        }

        if (
            $message->scheduled_at !== null
            && $message->scheduled_at->isFuture()
        ) {
            $delay = max(
                1,
                (int) now()->diffInSeconds(
                    $message->scheduled_at
                )
            );

            $this->release($delay);

            return;
        }

        $template = MessageTemplate::withoutGlobalScopes()
            ->findOrFail($message->message_template_id);

        $result = $client->send($message, $template);

        DB::transaction(function () use ($result): void {
            $message = OutboundMessage::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($this->outboundMessageId);

            if (
                ! in_array(
                    $message->status,
                    ['queued', 'scheduled'],
                    true
                )
            ) {
                return;
            }

            $message->forceFill([
                'provider' => 'infobip',
                'provider_message_id' => $result['message_id'],
                'status' => 'sent',
                'sent_at' => now(),
                'error_code' => null,
                'error_message' => null,
            ])->save();

            MessageDeliveryEvent::query()->create([
                'tenant_id' => $message->tenant_id,
                'outbound_message_id' => $message->id,
                'provider' => 'infobip',
                'event_type' => 'sent',
                'status' => 'sent',
                'metadata' => [
                    'provider_status' => $result['status'],
                    'provider_message_id' => $result['message_id'],
                ],
                'occurred_at' => now(),
                'received_at' => now(),
            ]);
        });
    }

    public function failed(Throwable $exception): void
    {
        $message = OutboundMessage::withoutGlobalScopes()
            ->find($this->outboundMessageId);

        if (
            $message === null
            || ! in_array(
                $message->status,
                ['queued', 'scheduled'],
                true
            )
        ) {
            return;
        }

        DB::transaction(function () use (
            $exception,
            $message
        ): void {
            $message->forceFill([
                'provider' => 'infobip',
                'status' => 'failed',
                'error_code' => $exception->getCode() !== 0
                    ? (string) $exception->getCode()
                    : 'infobip_delivery_failed',
                'error_message' => Str::limit(
                    $exception->getMessage(),
                    2000,
                    ''
                ),
                'failed_at' => now(),
            ])->save();

            MessageDeliveryEvent::query()->create([
                'tenant_id' => $message->tenant_id,
                'outbound_message_id' => $message->id,
                'provider' => 'infobip',
                'event_type' => 'failed',
                'status' => 'failed',
                'metadata' => [
                    'exception' => $exception::class,
                    'message' => Str::limit(
                        $exception->getMessage(),
                        1000,
                        ''
                    ),
                ],
                'occurred_at' => now(),
                'received_at' => now(),
            ]);
        });
    }
}