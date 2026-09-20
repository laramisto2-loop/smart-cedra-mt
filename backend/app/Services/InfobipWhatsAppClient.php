<?php

namespace App\Services;

use App\Models\MessageTemplate;
use App\Models\OutboundMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class InfobipWhatsAppClient
{
    /**
     * @return array{
     *     message_id: string,
     *     status: string,
     *     response: array<string, mixed>
     * }
     */
    public function send(
        OutboundMessage $message,
        MessageTemplate $template
    ): array {
        $baseUrl = rtrim(
            (string) config('services.infobip.base_url'),
            '/'
        );

        $apiKey = (string) config('services.infobip.api_key');
        $sender = (string) config(
            'services.infobip.whatsapp.sender'
        );

        $templateName = (string) config(
            'services.infobip.whatsapp.template'
        );

        if ($templateName === '') {
            $templateName = (string) $template
                ->provider_template_name;
        }

        if (
            $baseUrl === ''
            || $apiKey === ''
            || $sender === ''
            || $templateName === ''
        ) {
            throw new RuntimeException(
                'The Infobip WhatsApp configuration is incomplete.'
            );
        }

        $recipient = preg_replace(
            '/\D+/',
            '',
            (string) $message->recipient
        );

        if (! is_string($recipient) || $recipient === '') {
            throw new RuntimeException(
                'The WhatsApp recipient number is invalid.'
            );
        }

        $providerMessageId = (string) $message->client_uuid;

        $response = Http::withHeaders([
            'Authorization' => 'App '.$apiKey,
        ])
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->post(
                $baseUrl.'/whatsapp/1/message/template',
                [
                    'messages' => [
                        [
                            'from' => $sender,
                            'to' => $recipient,
                            'messageId' => $providerMessageId,
                            'content' => [
                                'templateName' => $templateName,
                                'templateData' => [
                                    'body' => [
                                        'placeholders' => [
                                            Str::limit(
                                                (string) $message
                                                    ->rendered_body,
                                                900,
                                                ''
                                            ),
                                        ],
                                    ],
                                ],
                                'language' => (string) (
                                    $template->language_code ?: 'en'
                                ),
                            ],
                        ],
                    ],
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                sprintf(
                    'Infobip rejected the WhatsApp message '
                    .'(HTTP %d): %s',
                    $response->status(),
                    Str::limit($response->body(), 1000, '')
                )
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException(
                'Infobip returned an invalid response.'
            );
        }

        $result = $payload['messages'][0] ?? $payload;

        if (! is_array($result)) {
            throw new RuntimeException(
                'Infobip returned an invalid message result.'
            );
        }

        return [
            'message_id' => (string) (
                $result['messageId'] ?? $providerMessageId
            ),
            'status' => (string) (
                $result['status']['groupName'] ?? 'PENDING'
            ),
            'response' => $payload,
        ];
    }
}