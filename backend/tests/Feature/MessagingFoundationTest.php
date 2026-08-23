<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactConsent;
use App\Models\MessageDeliveryEvent;
use App\Models\MessageTemplate;
use App\Models\OutboundMessage;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class MessagingFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            TenantSeeder::class,
            RbacSeeder::class,
        ]);
    }

    public function test_messaging_relationships_and_delivery_identity_work(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $contact = $this->createContact($admin);
        $consent = $this->createConsent($admin, $contact);
        $template = $this->createTemplate($admin);

        $clientUuid = Str::uuid()->toString();

        $message = $this->createMessage($admin, [
            'contact_id' => $contact->id,
            'message_template_id' => $template->id,
            'contact_consent_id' => $consent->id,
            'client_uuid' => $clientUuid,
        ]);

        $event = $this->createDeliveryEvent(
            $admin,
            $message
        );

        $message->refresh();
        $event->refresh();

        $this->assertSame(
            $admin->tenant_id,
            $message->tenant_id
        );

        $this->assertSame($clientUuid, $message->client_uuid);

        $this->assertMatchesRegularExpression(
            '/^MSG-[A-F0-9]{12}$/',
            $message->reference_code
        );

        $this->assertSame(
            $template->code,
            $message->template_code
        );

        $this->assertSame('granted', $message->consent_status);
        $this->assertNotNull($message->consent_checked_at);
        $this->assertNotNull($message->queued_at);

        $this->assertTrue($message->tenant->is($admin->tenant));
        $this->assertTrue($message->contact->is($contact));
        $this->assertTrue($message->template->is($template));
        $this->assertTrue($message->consent->is($consent));
        $this->assertTrue($message->sender->is($admin));

        $this->assertTrue(
            $message->deliveryEvents()
                ->firstOrFail()
                ->is($event)
        );

        $this->assertTrue(
            $event->outboundMessage->is($message)
        );

        $this->assertSame('mock-provider', $event->provider);
        $this->assertNotNull($event->occurred_at);
        $this->assertNotNull($event->received_at);

        $this->assertTrue(
            $admin->tenant->messageTemplates()
                ->firstOrFail()
                ->is($template)
        );

        $this->assertTrue(
            $admin->tenant->outboundMessages()
                ->firstOrFail()
                ->is($message)
        );

        $this->assertTrue(
            $admin->tenant->messageDeliveryEvents()
                ->firstOrFail()
                ->is($event)
        );

        $this->assertTrue(
            $admin->createdMessageTemplates()
                ->firstOrFail()
                ->is($template)
        );

        $this->assertTrue(
            $admin->sentOutboundMessages()
                ->firstOrFail()
                ->is($message)
        );

        $this->assertTrue(
            $contact->outboundMessages()
                ->firstOrFail()
                ->is($message)
        );

        $this->assertTrue(
            $consent->outboundMessages()
                ->firstOrFail()
                ->is($message)
        );
    }

    public function test_template_variables_are_derived_from_its_body(): void
    {
        $admin = $this->findUser('admin@cedra.test');

        $template = $this->createTemplate($admin, [
            'body' => 'Hello {{ first_name }}, your shift begins at {{shift_time}}. {{first_name}}',
            'variables' => ['incorrect_variable'],
        ]);

        $this->assertSame(
            ['first_name', 'shift_time'],
            $template->variables
        );

        $template->update([
            'body' => 'Hello {{full_name}}, this is your reminder.',
            'variables' => [],
        ]);

        $template->refresh();

        $this->assertSame(
            ['full_name'],
            $template->variables
        );
    }

    public function test_tenant_only_queries_its_own_messaging_records(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $cedraTemplate = $this->createTemplate($cedraAdmin);
        $cedraMessage = $this->createMessage(
            $cedraAdmin,
            ['message_template_id' => $cedraTemplate->id]
        );

        $cedraEvent = $this->createDeliveryEvent(
            $cedraAdmin,
            $cedraMessage
        );

        $futureTemplate = $this->createTemplate($futureAdmin);
        $futureMessage = $this->createMessage(
            $futureAdmin,
            ['message_template_id' => $futureTemplate->id]
        );

        $futureEvent = $this->createDeliveryEvent(
            $futureAdmin,
            $futureMessage
        );

        $this->actingAs($cedraAdmin);

        $this->assertCount(1, MessageTemplate::all());
        $this->assertCount(1, OutboundMessage::all());
        $this->assertCount(1, MessageDeliveryEvent::all());

        $this->assertTrue(
            MessageTemplate::firstOrFail()->is($cedraTemplate)
        );

        $this->assertTrue(
            OutboundMessage::firstOrFail()->is($cedraMessage)
        );

        $this->assertTrue(
            MessageDeliveryEvent::firstOrFail()->is($cedraEvent)
        );

        $this->assertNull(
            MessageTemplate::find($futureTemplate->id)
        );

        $this->assertNull(
            OutboundMessage::find($futureMessage->id)
        );

        $this->assertNull(
            MessageDeliveryEvent::find($futureEvent->id)
        );

        $this->assertSame(
            2,
            MessageTemplate::withoutGlobalScopes()->count()
        );

        $this->assertSame(
            2,
            OutboundMessage::withoutGlobalScopes()->count()
        );

        $this->assertSame(
            2,
            MessageDeliveryEvent::withoutGlobalScopes()->count()
        );
    }

    public function test_active_tenant_overrides_submitted_tenant_id(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $template = $this->createTemplate($cedraAdmin, [
            'tenant_id' => $futureAdmin->tenant_id,
        ]);

        $message = $this->createMessage($cedraAdmin, [
            'tenant_id' => $futureAdmin->tenant_id,
            'message_template_id' => $template->id,
        ]);

        $event = $this->createDeliveryEvent(
            $cedraAdmin,
            $message,
            ['tenant_id' => $futureAdmin->tenant_id]
        );

        $this->assertSame(
            $cedraAdmin->tenant_id,
            $template->tenant_id
        );

        $this->assertSame(
            $cedraAdmin->tenant_id,
            $message->tenant_id
        );

        $this->assertSame(
            $cedraAdmin->tenant_id,
            $event->tenant_id
        );
    }

    public function test_messaging_models_reject_cross_tenant_relationships(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $cedraContact = $this->createContact($cedraAdmin);
        $cedraConsent = $this->createConsent(
            $cedraAdmin,
            $cedraContact
        );

        $cedraTemplate = $this->createTemplate($cedraAdmin);

        $futureContact = $this->createContact($futureAdmin);
        $futureConsent = $this->createConsent(
            $futureAdmin,
            $futureContact
        );

        $futureTemplate = $this->createTemplate($futureAdmin);
        $futureMessage = $this->createMessage($futureAdmin, [
            'contact_id' => $futureContact->id,
            'contact_consent_id' => $futureConsent->id,
            'message_template_id' => $futureTemplate->id,
        ]);

        $this->assertTemplateCreationFails(
            $cedraAdmin,
            ['created_by_user_id' => $futureAdmin->id],
            'The message template creator must belong to the same tenant.'
        );

        $this->assertMessageCreationFails(
            $cedraAdmin,
            [
                'contact_id' => $futureContact->id,
                'contact_consent_id' => $cedraConsent->id,
                'message_template_id' => $cedraTemplate->id,
            ],
            'The outbound message contact must belong to the same tenant.'
        );

        $this->assertMessageCreationFails(
            $cedraAdmin,
            [
                'contact_id' => $cedraContact->id,
                'contact_consent_id' => $cedraConsent->id,
                'message_template_id' => $cedraTemplate->id,
                'sent_by_user_id' => $futureAdmin->id,
            ],
            'The outbound message sender must belong to the same tenant.'
        );

        $this->assertMessageCreationFails(
            $cedraAdmin,
            [
                'contact_id' => $cedraContact->id,
                'contact_consent_id' => $cedraConsent->id,
                'message_template_id' => $futureTemplate->id,
            ],
            'The outbound message template must belong to the same tenant.'
        );

        $this->assertMessageCreationFails(
            $cedraAdmin,
            [
                'contact_id' => $cedraContact->id,
                'contact_consent_id' => $futureConsent->id,
                'message_template_id' => $cedraTemplate->id,
            ],
            'The outbound message consent must belong to the same tenant.'
        );

        $this->assertDeliveryEventCreationFails(
            $cedraAdmin,
            $futureMessage,
            'The delivery event message must belong to the same tenant.'
        );
    }

    public function test_outbound_messages_require_matching_granted_consent(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $template = $this->createTemplate($admin);

        $noConsentContact = $this->createContact($admin);

        $this->assertMessageCreationFails(
            $admin,
            [
                'contact_id' => $noConsentContact->id,
                'message_template_id' => $template->id,
                'contact_consent_id' => null,
            ],
            'Granted consent is required before an outbound message may be queued.'
        );

        $deniedContact = $this->createContact($admin);
        $deniedConsent = $this->createConsent(
            $admin,
            $deniedContact,
            ['status' => 'denied']
        );

        $this->assertMessageCreationFails(
            $admin,
            [
                'contact_id' => $deniedContact->id,
                'message_template_id' => $template->id,
                'contact_consent_id' => $deniedConsent->id,
            ],
            'Granted consent is required before an outbound message may be queued.'
        );

        $smsContact = $this->createContact($admin);
        $smsConsent = $this->createConsent(
            $admin,
            $smsContact,
            [
                'channel' => 'sms',
                'status' => 'granted',
            ]
        );

        $this->assertMessageCreationFails(
            $admin,
            [
                'contact_id' => $smsContact->id,
                'message_template_id' => $template->id,
                'contact_consent_id' => $smsConsent->id,
            ],
            'The outbound message consent channel must match the message channel.'
        );

        $firstContact = $this->createContact($admin);
        $secondContact = $this->createContact($admin);

        $secondConsent = $this->createConsent(
            $admin,
            $secondContact
        );

        $this->assertMessageCreationFails(
            $admin,
            [
                'contact_id' => $firstContact->id,
                'message_template_id' => $template->id,
                'contact_consent_id' => $secondConsent->id,
            ],
            'The outbound message consent must belong to the selected contact.'
        );

        $suppressed = $this->createMessage($admin, [
            'contact_id' => $noConsentContact->id,
            'message_template_id' => $template->id,
            'contact_consent_id' => null,
            'status' => 'suppressed',
            'suppression_reason' => 'No granted WhatsApp consent.',
        ]);

        $this->assertSame('suppressed', $suppressed->status);
        $this->assertSame('unknown', $suppressed->consent_status);
        $this->assertNotNull($suppressed->consent_checked_at);
    }

    public function test_only_approved_matching_templates_can_be_sent(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $contact = $this->createContact($admin);
        $consent = $this->createConsent($admin, $contact);

        $draftTemplate = $this->createTemplate($admin, [
            'status' => 'draft',
        ]);

        $this->assertNull($draftTemplate->approved_at);

        $this->assertMessageCreationFails(
            $admin,
            [
                'contact_id' => $contact->id,
                'contact_consent_id' => $consent->id,
                'message_template_id' => $draftTemplate->id,
            ],
            'Only approved templates may be used for outbound messages.'
        );

        $smsTemplate = $this->createTemplate($admin, [
            'channel' => 'sms',
        ]);

        $this->assertMessageCreationFails(
            $admin,
            [
                'contact_id' => $contact->id,
                'contact_consent_id' => $consent->id,
                'message_template_id' => $smsTemplate->id,
            ],
            'The outbound message channel must match its template channel.'
        );

        $approvedTemplate = $this->createTemplate($admin);

        $this->assertNotNull($approvedTemplate->approved_at);

        $message = $this->createMessage($admin, [
            'contact_id' => $contact->id,
            'contact_consent_id' => $consent->id,
            'message_template_id' => $approvedTemplate->id,
        ]);

        $this->assertSame('queued', $message->status);
    }

    public function test_delivery_events_are_immutable_and_message_uuid_is_unique(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $contact = $this->createContact($admin);
        $consent = $this->createConsent($admin, $contact);
        $template = $this->createTemplate($admin);
        $clientUuid = Str::uuid()->toString();

        $message = $this->createMessage($admin, [
            'contact_id' => $contact->id,
            'contact_consent_id' => $consent->id,
            'message_template_id' => $template->id,
            'client_uuid' => $clientUuid,
        ]);

        $event = $this->createDeliveryEvent(
            $admin,
            $message
        );

        try {
            $event->update(['status' => 'read']);

            $this->fail(
                'A delivery event should not be updated.'
            );
        } catch (LogicException $exception) {
            $this->assertSame(
                'Message delivery events are immutable.',
                $exception->getMessage()
            );
        }

        $event->refresh();

        try {
            $event->delete();

            $this->fail(
                'A delivery event should not be deleted.'
            );
        } catch (LogicException $exception) {
            $this->assertSame(
                'Message delivery events cannot be deleted.',
                $exception->getMessage()
            );
        }

        try {
            $this->createMessage($admin, [
                'contact_id' => $contact->id,
                'contact_consent_id' => $consent->id,
                'message_template_id' => $template->id,
                'client_uuid' => $clientUuid,
            ]);

            $this->fail(
                'A duplicate offline UUID should not create another message.'
            );
        } catch (QueryException) {
            $this->assertSame(
                1,
                OutboundMessage::withoutGlobalScopes()->count()
            );
        }
    }

    public function test_policies_enforce_roles_status_and_tenants(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $admin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $coordinator = $this->createUserWithRole(
            $tenant,
            'coordinator'
        );

        $fieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $draftTemplate = $this->createTemplate($admin, [
            'status' => 'draft',
        ]);

        $approvedTemplate = $this->createTemplate($admin);
        $message = $this->createMessage($admin, [
            'message_template_id' => $approvedTemplate->id,
        ]);

        $event = $this->createDeliveryEvent(
            $admin,
            $message
        );

        $futureTemplate = $this->createTemplate($futureAdmin);
        $futureMessage = $this->createMessage($futureAdmin, [
            'message_template_id' => $futureTemplate->id,
        ]);

        $futureEvent = $this->createDeliveryEvent(
            $futureAdmin,
            $futureMessage
        );

        $this->actingAs($admin);

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'viewAny',
                MessageTemplate::class
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'create',
                MessageTemplate::class
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'update',
                $draftTemplate
            )
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'update',
                $approvedTemplate
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'approve',
                $draftTemplate
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'delete',
                $draftTemplate
            )
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'delete',
                $approvedTemplate
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'viewAny',
                OutboundMessage::class
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'create',
                OutboundMessage::class
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'view',
                $message
            )
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'update',
                $message
            )
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'delete',
                $message
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'view',
                $event
            )
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'create',
                MessageDeliveryEvent::class
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'viewAny',
                MessageTemplate::class
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'create',
                MessageTemplate::class
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'update',
                $draftTemplate
            )
        );

        $this->assertFalse(
            Gate::forUser($coordinator)->allows(
                'approve',
                $draftTemplate
            )
        );

        $this->assertFalse(
            Gate::forUser($coordinator)->allows(
                'delete',
                $draftTemplate
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'viewAny',
                OutboundMessage::class
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'create',
                OutboundMessage::class
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'view',
                $message
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'view',
                $event
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'viewAny',
                MessageTemplate::class
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'create',
                OutboundMessage::class
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'view',
                $message
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'view',
                $event
            )
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'view',
                $futureTemplate
            )
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'view',
                $futureMessage
            )
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'view',
                $futureEvent
            )
        );
    }

    private function createContact(
        User $actor,
        array $overrides = []
    ): Contact {
        $this->actingAs($actor);

        return Contact::create(array_merge([
            'created_by_user_id' => $actor->id,
            'reference_code' => 'MSG-CONTACT-'.Str::upper(
                Str::random(10)
            ),
            'first_name' => 'Maya',
            'last_name' => 'Haddad',
            'phone' => '+96170'.random_int(100000, 999999),
            'email' => Str::lower(Str::random(10)).'@example.test',
            'preferred_language' => 'en',
            'preferred_channel' => 'whatsapp',
            'status' => 'active',
            'source' => 'messaging_foundation_test',
        ], $overrides));
    }

    private function createConsent(
        User $actor,
        Contact $contact,
        array $overrides = []
    ): ContactConsent {
        $this->actingAs($actor);

        return ContactConsent::create(array_merge([
            'contact_id' => $contact->id,
            'recorded_by_user_id' => $actor->id,
            'channel' => 'whatsapp',
            'status' => 'granted',
            'source' => 'messaging_foundation_test',
            'consented_at' => now()->subDay(),
            'notes' => 'Fictional consent record.',
        ], $overrides));
    }

    private function createTemplate(
        User $actor,
        array $overrides = []
    ): MessageTemplate {
        $this->actingAs($actor);

        return MessageTemplate::create(array_merge([
            'created_by_user_id' => $actor->id,
            'name' => 'Volunteer reminder',
            'code' => 'MSG-TEMPLATE-'.Str::upper(
                Str::random(10)
            ),
            'channel' => 'whatsapp',
            'provider' => 'mock-provider',
            'provider_template_name' => 'volunteer_reminder',
            'language_code' => 'en',
            'category' => 'utility',
            'body' => 'Hello {{first_name}}, this is a reminder.',
            'variables' => ['first_name'],
            'status' => 'approved',
        ], $overrides));
    }

    private function createMessage(
        User $actor,
        array $overrides = []
    ): OutboundMessage {
        $this->actingAs($actor);

        if (! array_key_exists('contact_id', $overrides)) {
            $overrides['contact_id'] =
                $this->createContact($actor)->id;
        }

        $contact = Contact::withoutGlobalScopes()
            ->findOrFail($overrides['contact_id']);

        if (! array_key_exists(
            'message_template_id',
            $overrides
        )) {
            $overrides['message_template_id'] =
                $this->createTemplate($actor)->id;
        }

        if (! array_key_exists(
            'contact_consent_id',
            $overrides
        )) {
            $overrides['contact_consent_id'] =
                $this->createConsent($actor, $contact)->id;
        }

        return OutboundMessage::create(array_merge([
            'sent_by_user_id' => $actor->id,
            'client_uuid' => Str::uuid()->toString(),
            'channel' => 'whatsapp',
            'recipient' => $contact->phone
                ?? $contact->email
                ?? 'unknown-recipient',
            'rendered_body' => 'Hello Maya, this is a reminder.',
            'variables' => ['first_name' => 'Maya'],
            'source' => 'manual',
            'provider' => 'mock-provider',
            'status' => 'queued',
        ], $overrides));
    }

    private function createDeliveryEvent(
        User $actor,
        OutboundMessage $message,
        array $overrides = []
    ): MessageDeliveryEvent {
        $this->actingAs($actor);

        return MessageDeliveryEvent::create(array_merge([
            'outbound_message_id' => $message->id,
            'provider' => 'mock-provider',
            'provider_event_id' => 'EVENT-'.Str::upper(
                Str::random(16)
            ),
            'event_type' => 'delivered',
            'status' => 'delivered',
            'metadata' => [
                'provider_status' => 'delivered',
            ],
            'occurred_at' => now()->subMinute(),
        ], $overrides));
    }

    private function assertTemplateCreationFails(
        User $actor,
        array $overrides,
        string $expectedMessage
    ): void {
        try {
            $this->createTemplate($actor, $overrides);

            $this->fail(
                'The invalid message template should have been rejected.'
            );
        } catch (LogicException $exception) {
            $this->assertSame(
                $expectedMessage,
                $exception->getMessage()
            );
        }
    }

    private function assertMessageCreationFails(
        User $actor,
        array $overrides,
        string $expectedMessage
    ): void {
        try {
            $this->createMessage($actor, $overrides);

            $this->fail(
                'The invalid outbound message should have been rejected.'
            );
        } catch (LogicException $exception) {
            $this->assertSame(
                $expectedMessage,
                $exception->getMessage()
            );
        }
    }

    private function assertDeliveryEventCreationFails(
        User $actor,
        OutboundMessage $message,
        string $expectedMessage
    ): void {
        try {
            $this->createDeliveryEvent($actor, $message);

            $this->fail(
                'The invalid delivery event should have been rejected.'
            );
        } catch (LogicException $exception) {
            $this->assertSame(
                $expectedMessage,
                $exception->getMessage()
            );
        }
    }

    private function createUserWithRole(
        Tenant $tenant,
        string $roleSlug
    ): User {
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $role = Role::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $roleSlug)
            ->firstOrFail();

        $user->assignRole($role);

        return $user;
    }

    private function findUser(string $email): User
    {
        return User::query()
            ->where('email', $email)
            ->firstOrFail();
    }

    private function findTenant(string $slug): Tenant
    {
        return Tenant::query()
            ->where('slug', $slug)
            ->firstOrFail();
    }
}
