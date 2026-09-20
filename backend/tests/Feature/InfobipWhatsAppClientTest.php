<?php

namespace Tests\Feature;

use App\Models\MessageTemplate;
use App\Models\OutboundMessage;
use App\Services\InfobipWhatsAppClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class InfobipWhatsAppClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.infobip.base_url' =>
                'https://test.api.infobip.com',
            'services.infobip.api_key' =>
                'test-api-key',
            'services.infobip.whatsapp.sender' =>
                '447860088970',
            'services.infobip.whatsapp.template' =>
                'test_whatsapp_template_en',
        ]);
    }

    public function test_it_sends_the_correct_whatsapp_request(): void
    {
        Http::fake([
            'https://test.api.infobip.com/*' =>
                Http::response([
                    'messages' => [
                        [
                            'messageId' =>
                                'provider-message-123',
                            'status' => [
                                'groupName' => 'PENDING',
                            ],
                        ],
                    ],
                ], 200),
        ]);

        $message = new OutboundMessage([
            'client_uuid' =>
                '11111111-1111-4111-8111-111111111111',
            'recipient' => '+961 81 870 551',
            'rendered_body' =>
                'Hello Lara, this is an ElectoFlow test.',
        ]);

        $template = new MessageTemplate([
            'language_code' => 'en',
            'provider_template_name' =>
                'unused-provider-template',
        ]);

        $result = app(InfobipWhatsAppClient::class)
            ->send($message, $template);

        $this->assertSame(
            'provider-message-123',
            $result['message_id']
        );

        $this->assertSame('PENDING', $result['status']);

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->url()
                === 'https://test.api.infobip.com'
                    .'/whatsapp/1/message/template'
                && $request->hasHeader(
                    'Authorization',
                    'App test-api-key'
                )
                && data_get(
                    $data,
                    'messages.0.from'
                ) === '447860088970'
                && data_get(
                    $data,
                    'messages.0.to'
                ) === '96181870551'
                && data_get(
                    $data,
                    'messages.0.content.templateName'
                ) === 'test_whatsapp_template_en'
                && data_get(
                    $data,
                    'messages.0.content.templateData'
                        .'.body.placeholders.0'
                ) ===
                    'Hello Lara, this is an ElectoFlow test.'
                && data_get(
                    $data,
                    'messages.0.content.language'
                ) === 'en';
        });
    }

    public function test_it_throws_when_infobip_rejects_message(): void
    {
        Http::fake([
            'https://test.api.infobip.com/*' =>
                Http::response([
                    'requestError' => [
                        'serviceException' => [
                            'messageId' => 'BAD_REQUEST',
                            'text' => 'Invalid request.',
                        ],
                    ],
                ], 400),
        ]);

        $message = new OutboundMessage([
            'client_uuid' =>
                '22222222-2222-4222-8222-222222222222',
            'recipient' => '96181870551',
            'rendered_body' => 'Test message',
        ]);

        $template = new MessageTemplate([
            'language_code' => 'en',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Infobip rejected the WhatsApp message (HTTP 400)'
        );

        app(InfobipWhatsAppClient::class)
            ->send($message, $template);
    }
}