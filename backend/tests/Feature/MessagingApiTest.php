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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessagingApiTest extends TestCase
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_unauthenticated_user_cannot_access_messaging_api(): void
    {
        $this->getJson('/api/message-templates')
            ->assertUnauthorized();

        $this->postJson('/api/message-templates', [])
            ->assertUnauthorized();

        $this->getJson('/api/outbound-messages')
            ->assertUnauthorized();

        $this->postJson('/api/outbound-messages', [])
            ->assertUnauthorized();
    }

    public function test_user_without_messaging_permissions_is_forbidden(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($user)
            ->getJson('/api/message-templates')
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson('/api/message-templates', [])
            ->assertForbidden();

        $this->actingAs($user)
            ->getJson('/api/outbound-messages')
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson('/api/outbound-messages', [])
            ->assertForbidden();
    }

    public function test_admin_manages_templates_and_only_sees_own_tenant(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $futureTemplate = $this->createTemplate(
            $futureAdmin,
            [
                'name' => 'Future tenant reminder',
                'code' => 'FUTURE_REMINDER',
            ]
        );

        $response = $this->actingAs($admin)
            ->postJson(
                '/api/message-templates',
                $this->validTemplatePayload([
                    'code' => 'volunteer_reminder',
                ])
            )
            ->assertCreated()
            ->assertJsonPath('data.code', 'VOLUNTEER_REMINDER')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath(
                'data.created_by_user_id',
                $admin->id
            );

        $templateId = $response->json('data.id');

        $this->actingAs($admin)
            ->patchJson(
                "/api/message-templates/{$templateId}",
                [
                    'name' => 'Updated volunteer reminder',
                    'body' => 'Hello {{first_name}}, updated reminder.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Updated volunteer reminder'
            );

        $this->actingAs($admin)
            ->patchJson(
                "/api/message-templates/{$templateId}/approve",
                [
                    'status' => 'approved',
                ]
            )
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $response = $this->actingAs($admin)
            ->getJson(
                '/api/message-templates?search=Updated&channel=whatsapp&status=approved'
            )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $templateId);

        $templateIds = collect($response->json('data'))
            ->pluck('id')
            ->all();

        $this->assertNotContains(
            $futureTemplate->id,
            $templateIds
        );

        $this->actingAs($admin)
            ->getJson(
                "/api/message-templates/{$futureTemplate->id}"
            )
            ->assertNotFound();

        $this->actingAs($admin)
            ->patchJson(
                "/api/message-templates/{$templateId}",
                [
                    'name' => 'Approved templates are immutable',
                ]
            )
            ->assertForbidden();

        $this->actingAs($admin)
            ->deleteJson(
                "/api/message-templates/{$templateId}"
            )
            ->assertNoContent();

        $this->assertDatabaseMissing('message_templates', [
            'id' => $templateId,
        ]);
    }

    public function test_coordinator_can_draft_but_cannot_approve_or_delete_templates(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $coordinator = $this->createUserWithRole(
            $tenant,
            'coordinator'
        );

        $response = $this->actingAs($coordinator)
            ->postJson(
                '/api/message-templates',
                $this->validTemplatePayload([
                    'code' => 'COORDINATOR_DRAFT',
                ])
            )
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        $templateId = $response->json('data.id');

        $this->actingAs($coordinator)
            ->patchJson(
                "/api/message-templates/{$templateId}",
                [
                    'name' => 'Coordinator edited draft',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Coordinator edited draft'
            );

        $this->actingAs($coordinator)
            ->patchJson(
                "/api/message-templates/{$templateId}/approve",
                [
                    'status' => 'approved',
                ]
            )
            ->assertForbidden();

        $this->actingAs($coordinator)
            ->deleteJson(
                "/api/message-templates/{$templateId}"
            )
            ->assertForbidden();
    }

    public function test_approved_template_sends_rendered_idempotent_message_with_consent(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-15 09:00:00', 'UTC')
        );

        $admin = $this->findUser('admin@cedra.test');
        $contact = $this->createContact($admin);
        $this->createConsent($admin, $contact);

        $template = $this->createTemplate($admin, [
            'body' => 'Hello {{ first_name }}, ref {{reference_code}}: {{custom_note}}.',
            'variables' => [
                'first_name',
                'reference_code',
                'custom_note',
            ],
        ]);

        $clientUuid = Str::uuid()->toString();

        $payload = [
            'contact_id' => $contact->id,
            'message_template_id' => $template->id,
            'client_uuid' => $clientUuid,
            'variables' => [
                'custom_note' => 'Bring water',
            ],
        ];

        $response = $this->actingAs($admin)
            ->postJson('/api/outbound-messages', $payload)
            ->assertCreated()
            ->assertJsonPath('data.client_uuid', $clientUuid)
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.consent_status', 'granted')
            ->assertJsonPath('data.channel', 'whatsapp')
            ->assertJsonPath(
                'data.rendered_body',
                "Hello Maya, ref {$contact->reference_code}: Bring water."
            )
            ->assertJsonPath(
                'data.variables.custom_note',
                'Bring water'
            )
            ->assertJsonCount(1, 'data.delivery_events')
            ->assertJsonPath(
                'data.delivery_events.0.event_type',
                'queued'
            );

        $messageId = $response->json('data.id');

        $this->actingAs($admin)
            ->postJson('/api/outbound-messages', $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $messageId);

        $this->assertDatabaseCount('outbound_messages', 1);
        $this->assertDatabaseCount('message_delivery_events', 1);
    }

    public function test_message_without_granted_consent_is_suppressed_and_audited(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-15 09:00:00', 'UTC')
        );

        $admin = $this->findUser('admin@cedra.test');
        $contact = $this->createContact($admin);

        $consent = $this->createConsent($admin, $contact, [
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);

        $template = $this->createTemplate($admin);

        $response = $this->actingAs($admin)
            ->postJson('/api/outbound-messages', [
                'contact_id' => $contact->id,
                'message_template_id' => $template->id,
                'client_uuid' => Str::uuid()->toString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'suppressed')
            ->assertJsonPath('data.consent_status', 'revoked')
            ->assertJsonPath(
                'data.contact_consent_id',
                $consent->id
            )
            ->assertJsonPath(
                'data.suppression_reason',
                "The contact's whatsapp consent status is revoked."
            )
            ->assertJsonCount(0, 'data.delivery_events');

        $this->assertDatabaseHas('outbound_messages', [
            'id' => $response->json('data.id'),
            'status' => 'suppressed',
            'consent_status' => 'revoked',
        ]);

        $this->assertDatabaseCount('message_delivery_events', 0);
    }

    public function test_quiet_hours_schedule_message_for_tenant_morning(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-15 20:00:00', 'UTC')
        );

        $admin = $this->findUser('admin@cedra.test');
        $contact = $this->createContact($admin);
        $this->createConsent($admin, $contact);
        $template = $this->createTemplate($admin);

        $response = $this->actingAs($admin)
            ->postJson('/api/outbound-messages', [
                'contact_id' => $contact->id,
                'message_template_id' => $template->id,
                'client_uuid' => Str::uuid()->toString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath(
                'data.delivery_events.0.event_type',
                'scheduled'
            );

        $message = OutboundMessage::query()
            ->findOrFail($response->json('data.id'));

        $this->assertTrue(
            $message->scheduled_at->equalTo(
                Carbon::parse('2026-08-16 05:00:00', 'UTC')
            )
        );

        $this->assertNull($message->queued_at);
    }

    public function test_message_filters_delivery_history_and_tenant_isolation_work(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-15 09:00:00', 'UTC')
        );

        $admin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $contact = $this->createContact($admin, [
            'first_name' => 'Layla',
            'last_name' => 'Nasser',
        ]);

        $this->createConsent($admin, $contact);
        $template = $this->createTemplate($admin);

        $messageResponse = $this->actingAs($admin)
            ->postJson('/api/outbound-messages', [
                'contact_id' => $contact->id,
                'message_template_id' => $template->id,
                'client_uuid' => Str::uuid()->toString(),
            ])
            ->assertCreated();

        $messageId = $messageResponse->json('data.id');

        $this->actingAs($admin);

        MessageDeliveryEvent::query()->create([
            'outbound_message_id' => $messageId,
            'provider' => 'mock-provider',
            'provider_event_id' => 'DELIVERED-'.Str::upper(
                Str::random(12)
            ),
            'event_type' => 'delivered',
            'status' => 'delivered',
            'metadata' => [
                'provider_status' => 'delivered',
            ],
            'occurred_at' => now()->addMinute(),
        ]);

        $futureContact = $this->createContact($futureAdmin);
        $this->createConsent($futureAdmin, $futureContact);
        $futureTemplate = $this->createTemplate($futureAdmin);

        $futureMessageResponse = $this->actingAs($futureAdmin)
            ->postJson('/api/outbound-messages', [
                'contact_id' => $futureContact->id,
                'message_template_id' => $futureTemplate->id,
                'client_uuid' => Str::uuid()->toString(),
            ])
            ->assertCreated();

        $futureMessageId = $futureMessageResponse->json('data.id');

        $this->actingAs($admin)
            ->getJson(
                "/api/outbound-messages?search=Layla&channel=whatsapp&status=queued&contact_id={$contact->id}"
            )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $messageId)
            ->assertJsonMissing([
                'id' => $futureMessageId,
            ]);

        $this->actingAs($admin)
            ->getJson(
                "/api/outbound-messages/{$messageId}/delivery-events"
            )
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.event_type', 'queued')
            ->assertJsonPath('data.1.event_type', 'delivered');

        $this->actingAs($admin)
            ->getJson(
                "/api/outbound-messages/{$futureMessageId}"
            )
            ->assertNotFound();
    }

    public function test_validation_protects_internal_fields_relationships_and_template_variables(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-15 09:00:00', 'UTC')
        );

        $admin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $contact = $this->createContact($admin);
        $this->createConsent($admin, $contact);

        $template = $this->createTemplate($admin, [
            'body' => 'Hello {{first_name}}, event {{event_name}}.',
            'variables' => [
                'first_name',
                'event_name',
            ],
        ]);

        $futureContact = $this->createContact($futureAdmin);
        $futureTemplate = $this->createTemplate($futureAdmin);

        $this->actingAs($admin)
            ->postJson('/api/outbound-messages', [
                'contact_id' => $contact->id,
                'message_template_id' => $template->id,
                'tenant_id' => $futureAdmin->tenant_id,
                'status' => 'delivered',
                'recipient' => '+96170000000',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'tenant_id',
                'status',
                'recipient',
            ]);

        $this->actingAs($admin)
            ->postJson('/api/outbound-messages', [
                'contact_id' => $futureContact->id,
                'message_template_id' => $futureTemplate->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'contact_id',
                'message_template_id',
            ]);

        $this->actingAs($admin)
            ->postJson('/api/outbound-messages', [
                'contact_id' => $contact->id,
                'message_template_id' => $template->id,
                'variables' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'variables.event_name',
            ]);

        $this->actingAs($admin)
            ->getJson('/api/message-templates?channel=email')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('channel');

        $this->actingAs($admin)
            ->getJson('/api/outbound-messages?status=processing')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    private function validTemplatePayload(
        array $overrides = []
    ): array {
        return array_merge([
            'name' => 'Volunteer reminder',
            'code' => 'VOLUNTEER_REMINDER_'.Str::upper(
                Str::random(8)
            ),
            'channel' => 'whatsapp',
            'provider' => 'mock-provider',
            'provider_template_name' => 'volunteer_reminder',
            'language_code' => 'en',
            'category' => 'utility',
            'body' => 'Hello {{first_name}}, this is a reminder.',
            'variables' => [
                'first_name',
            ],
        ], $overrides);
    }

    private function createContact(
        User $actor,
        array $overrides = []
    ): Contact {
        $this->actingAs($actor);

        return Contact::query()->create(array_merge([
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
            'source' => 'messaging_api_test',
        ], $overrides));
    }

    private function createConsent(
        User $actor,
        Contact $contact,
        array $overrides = []
    ): ContactConsent {
        $this->actingAs($actor);

        return ContactConsent::query()->create(array_merge([
            'contact_id' => $contact->id,
            'recorded_by_user_id' => $actor->id,
            'channel' => 'whatsapp',
            'status' => 'granted',
            'source' => 'messaging_api_test',
            'consented_at' => now()->subDay(),
            'notes' => 'Fictional messaging API consent.',
        ], $overrides));
    }

    private function createTemplate(
        User $actor,
        array $overrides = []
    ): MessageTemplate {
        $this->actingAs($actor);

        return MessageTemplate::query()->create(array_merge([
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
            'variables' => [
                'first_name',
            ],
            'status' => 'approved',
        ], $overrides));
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
// The suite covers authentication, permissions, tenant isolation, template approval, consent suppression, variable rendering, idempotency, quiet-hour scheduling, filtering, and delivery history
