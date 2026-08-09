<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactConsent;
use App\Models\ContactInteraction;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactInteractionApiTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_interaction_api(): void
    {
        $this->getJson('/api/contacts/1/interactions')
            ->assertUnauthorized();

        $this->postJson('/api/contacts/1/interactions', [])
            ->assertUnauthorized();

        $this->getJson('/api/contact-interactions/1')
            ->assertUnauthorized();

        $this->patchJson('/api/contact-interactions/1', [])
            ->assertUnauthorized();

        $this->deleteJson('/api/contact-interactions/1')
            ->assertUnauthorized();
    }

    public function test_admin_only_receives_and_filters_own_contact_timeline(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $cedraContact = $this->createContact(
            $cedraTenant,
            'CEDRA-TIMELINE'
        );

        $futureContact = $this->createContact(
            $futureTenant,
            'FUTURE-TIMELINE'
        );

        $olderInteraction = $this->createInteraction(
            $cedraTenant,
            $cedraContact,
            $cedraAdmin,
            [
                'channel' => 'note',
                'direction' => 'internal',
                'outcome' => 'informational',
                'subject' => 'Older note',
                'occurred_at' => now()->subDays(2),
            ]
        );

        $newerInteraction = $this->createInteraction(
            $cedraTenant,
            $cedraContact,
            $cedraAdmin,
            [
                'channel' => 'phone',
                'direction' => 'inbound',
                'outcome' => 'completed',
                'subject' => 'Recent phone call',
                'occurred_at' => now()->subDay(),
            ]
        );

        $futureInteraction = $this->createInteraction(
            $futureTenant,
            $futureContact,
            $futureAdmin
        );

        $this->actingAs($cedraAdmin)
            ->getJson(
                "/api/contacts/{$cedraContact->id}/interactions"
            )
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath(
                'data.0.id',
                $newerInteraction->id
            )
            ->assertJsonPath(
                'data.1.id',
                $olderInteraction->id
            )
            ->assertJsonMissing([
                'id' => $futureInteraction->id,
            ]);

        $this->getJson(
            "/api/contacts/{$cedraContact->id}/interactions"
            .'?channel=phone'
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $newerInteraction->id
            );

        $this->getJson(
            "/api/contacts/{$cedraContact->id}/interactions"
            .'?direction=internal&outcome=informational'
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $olderInteraction->id
            );
    }

    public function test_admin_records_consent_aware_interactions(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $admin = $this->findUser('admin@cedra.test');

        $consentedContact = $this->createContact(
            $tenant,
            'CONSENTED-INTERACTION'
        );

        $unknownContact = $this->createContact(
            $tenant,
            'UNKNOWN-INTERACTION'
        );

        $this->createConsent(
            $tenant,
            $consentedContact,
            'whatsapp',
            'granted'
        );

        $createResponse = $this->actingAs($admin)
            ->postJson(
                "/api/contacts/{$consentedContact->id}/interactions",
                [
                    'channel' => 'whatsapp',
                    'direction' => 'outbound',
                    'outcome' => 'completed',
                    'subject' => 'Volunteer confirmation',
                    'notes' => 'Confirmed attendance.',
                    'duration_seconds' => 120,
                    'occurred_at' => now()
                        ->subMinutes(5)
                        ->toISOString(),
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.contact_id',
                $consentedContact->id
            )
            ->assertJsonPath('data.channel', 'whatsapp')
            ->assertJsonPath('data.direction', 'outbound')
            ->assertJsonPath(
                'data.consent_status_snapshot',
                'granted'
            )
            ->assertJsonPath(
                'data.recorded_by.id',
                $admin->id
            );

        $interactionId = $createResponse->json('data.id');

        $this->assertDatabaseHas('contact_interactions', [
            'id' => $interactionId,
            'tenant_id' => $tenant->id,
            'contact_id' => $consentedContact->id,
            'recorded_by_user_id' => $admin->id,
            'consent_status_snapshot' => 'granted',
        ]);

        $this->assertNotNull(
            ContactInteraction::withoutGlobalScopes()
                ->findOrFail($interactionId)
                ->consent_checked_at
        );

        $this->postJson(
            "/api/contacts/{$unknownContact->id}/interactions",
            [
                'channel' => 'email',
                'direction' => 'outbound',
                'outcome' => 'completed',
                'occurred_at' => now()->toISOString(),
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('channel');

        $inboundResponse = $this->postJson(
            "/api/contacts/{$unknownContact->id}/interactions",
            [
                'channel' => 'email',
                'direction' => 'inbound',
                'outcome' => 'completed',
                'subject' => 'Incoming email',
                'occurred_at' => now()->toISOString(),
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.consent_status_snapshot',
                'unknown'
            );

        $this->assertDatabaseHas('contact_interactions', [
            'id' => $inboundResponse->json('data.id'),
            'consent_status_snapshot' => 'unknown',
        ]);

        $internalResponse = $this->postJson(
            "/api/contacts/{$unknownContact->id}/interactions",
            [
                'channel' => 'note',
                'direction' => 'internal',
                'outcome' => 'informational',
                'subject' => 'Internal CRM note',
                'occurred_at' => now()->toISOString(),
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.consent_status_snapshot',
                null
            )
            ->assertJsonPath(
                'data.consent_checked_at',
                null
            );

        $this->assertDatabaseHas('contact_interactions', [
            'id' => $internalResponse->json('data.id'),
            'channel' => 'note',
            'direction' => 'internal',
        ]);
    }

    public function test_interaction_validation_protects_internal_fields(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $admin = $this->findUser('admin@cedra.test');

        $contact = $this->createContact(
            $tenant,
            'INTERACTION-VALIDATION'
        );

        $this->actingAs($admin)
            ->postJson(
                "/api/contacts/{$contact->id}/interactions",
                [
                    'tenant_id' => $tenant->id,
                    'contact_id' => $contact->id,
                    'recorded_by_user_id' => $admin->id,
                    'consent_status_snapshot' => 'granted',
                    'consent_checked_at' => now()->toISOString(),
                    'channel' => 'telegram',
                    'direction' => 'sideways',
                    'outcome' => 'maybe',
                    'duration_seconds' => -5,
                    'occurred_at' => now()
                        ->addDay()
                        ->toISOString(),
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'tenant_id',
                'contact_id',
                'recorded_by_user_id',
                'consent_status_snapshot',
                'consent_checked_at',
                'channel',
                'direction',
                'outcome',
                'duration_seconds',
                'occurred_at',
            ]);

        $this->assertDatabaseCount(
            'contact_interactions',
            0
        );
    }

    public function test_tenant_admin_can_view_update_and_delete_interaction(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $admin = $this->findUser('admin@cedra.test');

        $contact = $this->createContact(
            $tenant,
            'INTERACTION-CRUD'
        );

        $interaction = $this->createInteraction(
            $tenant,
            $contact,
            $admin,
            [
                'subject' => 'Original subject',
                'notes' => 'Original notes',
            ]
        );

        $this->actingAs($admin)
            ->getJson(
                "/api/contact-interactions/{$interaction->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.subject',
                'Original subject'
            )
            ->assertJsonPath(
                'data.recorded_by.id',
                $admin->id
            );

        $this->patchJson(
            "/api/contact-interactions/{$interaction->id}",
            [
                'subject' => 'Updated subject',
                'notes' => 'Updated notes',
                'outcome' => 'follow_up',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.subject',
                'Updated subject'
            )
            ->assertJsonPath(
                'data.outcome',
                'follow_up'
            );

        $this->assertDatabaseHas('contact_interactions', [
            'id' => $interaction->id,
            'subject' => 'Updated subject',
            'notes' => 'Updated notes',
            'outcome' => 'follow_up',
        ]);

        $this->deleteJson(
            "/api/contact-interactions/{$interaction->id}"
        )
            ->assertNoContent();

        $this->assertDatabaseMissing('contact_interactions', [
            'id' => $interaction->id,
        ]);
    }

    public function test_coordinator_can_manage_but_not_delete_interactions(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $admin = $this->findUser('admin@cedra.test');

        $coordinator = $this->createUserWithRole(
            $tenant,
            'coordinator'
        );

        $contact = $this->createContact(
            $tenant,
            'COORDINATOR-INTERACTION'
        );

        $existingInteraction = $this->createInteraction(
            $tenant,
            $contact,
            $admin
        );

        $createResponse = $this->actingAs($coordinator)
            ->postJson(
                "/api/contacts/{$contact->id}/interactions",
                [
                    'channel' => 'note',
                    'direction' => 'internal',
                    'outcome' => 'informational',
                    'subject' => 'Coordinator note',
                    'occurred_at' => now()->toISOString(),
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.recorded_by.id',
                $coordinator->id
            );

        $this->getJson(
            "/api/contacts/{$contact->id}/interactions"
        )
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->patchJson(
            "/api/contact-interactions/{$existingInteraction->id}",
            [
                'subject' => 'Coordinator correction',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.subject',
                'Coordinator correction'
            );

        $this->deleteJson(
            "/api/contact-interactions/{$existingInteraction->id}"
        )
            ->assertForbidden();

        $this->assertDatabaseHas('contact_interactions', [
            'id' => $existingInteraction->id,
        ]);

        $this->assertDatabaseHas('contact_interactions', [
            'id' => $createResponse->json('data.id'),
            'recorded_by_user_id' => $coordinator->id,
        ]);
    }

    public function test_field_agent_cannot_access_interaction_api(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $admin = $this->findUser('admin@cedra.test');

        $fieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $contact = $this->createContact(
            $tenant,
            'FIELD-INTERACTION'
        );

        $interaction = $this->createInteraction(
            $tenant,
            $contact,
            $admin
        );

        $this->actingAs($fieldAgent)
            ->getJson(
                "/api/contacts/{$contact->id}/interactions"
            )
            ->assertForbidden();

        $this->postJson(
            "/api/contacts/{$contact->id}/interactions",
            [
                'channel' => 'note',
                'direction' => 'internal',
                'occurred_at' => now()->toISOString(),
            ]
        )
            ->assertForbidden();

        $this->getJson(
            "/api/contact-interactions/{$interaction->id}"
        )
            ->assertForbidden();

        $this->patchJson(
            "/api/contact-interactions/{$interaction->id}",
            [
                'subject' => 'Forbidden',
            ]
        )
            ->assertForbidden();

        $this->deleteJson(
            "/api/contact-interactions/{$interaction->id}"
        )
            ->assertForbidden();
    }

    public function test_admin_cannot_access_another_tenants_interactions(): void
    {
        $futureTenant = $this->findTenant('lebanon-future');
        $futureAdmin = $this->findUser('admin@future.test');

        $futureContact = $this->createContact(
            $futureTenant,
            'FUTURE-INTERACTION-API'
        );

        $futureInteraction = $this->createInteraction(
            $futureTenant,
            $futureContact,
            $futureAdmin
        );

        $this->actingAs(
            $this->findUser('admin@cedra.test')
        )
            ->getJson(
                "/api/contacts/{$futureContact->id}/interactions"
            )
            ->assertNotFound();

        $this->postJson(
            "/api/contacts/{$futureContact->id}/interactions",
            [
                'channel' => 'note',
                'direction' => 'internal',
                'occurred_at' => now()->toISOString(),
            ]
        )
            ->assertNotFound();

        $this->getJson(
            "/api/contact-interactions/{$futureInteraction->id}"
        )
            ->assertNotFound();

        $this->patchJson(
            "/api/contact-interactions/{$futureInteraction->id}",
            [
                'subject' => 'Forbidden',
            ]
        )
            ->assertNotFound();

        $this->deleteJson(
            "/api/contact-interactions/{$futureInteraction->id}"
        )
            ->assertNotFound();
    }

    public function test_invalid_interaction_filters_are_rejected(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $contact = $this->createContact(
            $tenant,
            'FILTER-INTERACTION'
        );

        $this->actingAs(
            $this->findUser('admin@cedra.test')
        )
            ->getJson(
                "/api/contacts/{$contact->id}/interactions"
                .'?channel=telegram'
                .'&direction=sideways'
                .'&outcome=maybe'
                .'&date_from=not-a-date'
                .'&date_to=also-not-a-date'
                .'&per_page=500'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'channel',
                'direction',
                'outcome',
                'date_from',
                'date_to',
                'per_page',
            ]);
    }

    private function findTenant(string $slug): Tenant
    {
        return Tenant::query()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    private function findUser(string $email): User
    {
        return User::query()
            ->where('email', $email)
            ->firstOrFail();
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createContact(
        Tenant $tenant,
        string $referenceCode,
        array $attributes = []
    ): Contact {
        return Contact::withoutGlobalScopes()->create(
            array_merge(
                [
                    'first_name' => 'Test',
                    'last_name' => 'Contact',
                    'preferred_language' => 'en',
                    'status' => 'active',
                ],
                $attributes,
                [
                    'tenant_id' => $tenant->id,
                    'reference_code' => $referenceCode,
                ]
            )
        );
    }

    private function createConsent(
        Tenant $tenant,
        Contact $contact,
        string $channel,
        string $status
    ): ContactConsent {
        return ContactConsent::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'contact_id' => $contact->id,
            'channel' => $channel,
            'status' => $status,
            'source' => 'test',
            'consented_at' => $status === 'granted'
                ? now()
                : null,
            'revoked_at' => $status === 'revoked'
                ? now()
                : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createInteraction(
        Tenant $tenant,
        Contact $contact,
        User $recorder,
        array $attributes = []
    ): ContactInteraction {
        return ContactInteraction::withoutGlobalScopes()->create(
            array_merge(
                [
                    'channel' => 'note',
                    'direction' => 'internal',
                    'outcome' => 'informational',
                    'subject' => 'Test interaction',
                    'occurred_at' => now()->subHour(),
                ],
                $attributes,
                [
                    'tenant_id' => $tenant->id,
                    'contact_id' => $contact->id,
                    'recorded_by_user_id' => $recorder->id,
                ]
            )
        );
    }
}
