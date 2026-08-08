<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Contact;
use App\Models\ContactConsent;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\GeographySeeder;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            TenantSeeder::class,
            RbacSeeder::class,
            GeographySeeder::class,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_contact_api(): void
    {
        $this->getJson('/api/contacts')
            ->assertUnauthorized();

        $this->postJson('/api/contacts', [])
            ->assertUnauthorized();

        $this->postJson('/api/contacts/1/consents', [])
            ->assertUnauthorized();
    }

    public function test_admin_only_receives_searches_and_filters_own_contacts(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');
        $cedraArea = $this->findArea($cedraTenant);

        $layla = $this->createContact(
            $cedraTenant,
            'CEDRA-0001',
            [
                'area_id' => $cedraArea->id,
                'first_name' => 'Layla',
                'last_name' => 'Haddad',
                'phone' => '+96170111111',
                'preferred_language' => 'ar',
                'preferred_channel' => 'whatsapp',
                'status' => 'active',
            ]
        );

        $this->createConsent(
            $cedraTenant,
            $layla,
            'whatsapp',
            'granted'
        );

        $secondContact = $this->createContact(
            $cedraTenant,
            'CEDRA-0002',
            [
                'first_name' => 'Nadim',
                'last_name' => 'Saab',
                'email' => 'nadim@example.test',
                'preferred_language' => 'en',
                'preferred_channel' => 'email',
                'status' => 'inactive',
            ]
        );

        $this->createConsent(
            $cedraTenant,
            $secondContact,
            'email',
            'denied'
        );

        $this->createContact(
            $futureTenant,
            'FUTURE-0001',
            [
                'first_name' => 'Future',
                'last_name' => 'Contact',
            ]
        );

        $this->actingAs($this->cedraAdmin())
            ->getJson('/api/contacts')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing([
                'reference_code' => 'FUTURE-0001',
            ]);

        $this->getJson('/api/contacts?search=Layla')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.reference_code',
                'CEDRA-0001'
            );

        $this->getJson('/api/contacts?status=inactive')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.reference_code',
                'CEDRA-0002'
            );

        $this->getJson(
            "/api/contacts?area_id={$cedraArea->id}"
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.reference_code',
                'CEDRA-0001'
            );

        $this->getJson(
            '/api/contacts?consent_channel=whatsapp'
            .'&consent_status=granted'
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.reference_code',
                'CEDRA-0001'
            );

        $this->getJson('/api/contacts?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.per_page', 1);
    }

    public function test_tenant_admin_can_create_update_view_and_delete_contact(): void
    {
        $admin = $this->cedraAdmin();
        $tenant = $admin->tenant;
        $area = $this->findArea($tenant);

        $createResponse = $this->actingAs($admin)
            ->postJson('/api/contacts', [
                'reference_code' => 'CEDRA-0100',
                'area_id' => $area->id,
                'first_name' => 'Maya',
                'last_name' => 'Nassar',
                'name_ar' => 'مايا نصار',
                'phone' => '+96170123456',
                'email' => 'maya@example.test',
                'address' => 'Beirut',
                'preferred_language' => 'ar',
                'preferred_channel' => 'whatsapp',
                'status' => 'active',
                'source' => 'field_registration',
                'notes' => 'Initial registration',
            ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath(
                'data.reference_code',
                'CEDRA-0100'
            )
            ->assertJsonPath('data.full_name', 'Maya Nassar')
            ->assertJsonPath('data.area.id', $area->id)
            ->assertJsonPath('data.creator.id', $admin->id)
            ->assertJsonCount(0, 'data.consents');

        $contactId = $createResponse->json('data.id');

        $this->assertDatabaseHas('contacts', [
            'id' => $contactId,
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $admin->id,
            'reference_code' => 'CEDRA-0100',
        ]);

        $this->getJson("/api/contacts/{$contactId}")
            ->assertOk()
            ->assertJsonPath(
                'data.reference_code',
                'CEDRA-0100'
            );

        $this->patchJson("/api/contacts/{$contactId}", [
            'first_name' => 'Maya Updated',
            'status' => 'inactive',
            'preferred_channel' => 'email',
        ])
            ->assertOk()
            ->assertJsonPath(
                'data.first_name',
                'Maya Updated'
            )
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath(
                'data.preferred_channel',
                'email'
            );

        $this->postJson(
            "/api/contacts/{$contactId}/consents",
            [
                'channel' => 'email',
                'status' => 'granted',
                'source' => 'written_form',
            ]
        )
            ->assertOk();

        $this->deleteJson("/api/contacts/{$contactId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('contacts', [
            'id' => $contactId,
        ]);

        $this->assertDatabaseMissing('contact_consents', [
            'contact_id' => $contactId,
        ]);
    }

    public function test_contact_validation_protects_tenant_and_internal_fields(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');
        $futureArea = $this->findArea($futureTenant);
        $futureAdmin = $this->futureAdmin();

        $this->createContact(
            $cedraTenant,
            'DUPLICATE'
        );

        $this->createContact(
            $futureTenant,
            'DUPLICATE'
        );

        $this->actingAs($this->cedraAdmin())
            ->postJson('/api/contacts', [
                'tenant_id' => $futureTenant->id,
                'created_by_user_id' => $futureAdmin->id,
                'reference_code' => 'INVALID-INTERNAL',
                'area_id' => $futureArea->id,
                'first_name' => 'Invalid',
                'last_name' => 'Contact',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'tenant_id',
                'created_by_user_id',
                'area_id',
            ]);

        $this->postJson('/api/contacts', [
            'reference_code' => 'DUPLICATE',
            'first_name' => 'Duplicate',
            'last_name' => 'Contact',
            'email' => 'not-an-email',
            'preferred_language' => 'fr',
            'preferred_channel' => 'telegram',
            'status' => 'deleted',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'reference_code',
                'email',
                'preferred_language',
                'preferred_channel',
                'status',
            ]);
    }

    public function test_admin_can_record_grant_and_revocation_without_forged_metadata(): void
    {
        $admin = $this->cedraAdmin();
        $contact = $this->createContact(
            $admin->tenant,
            'CEDRA-CONSENT'
        );

        $grantResponse = $this->actingAs($admin)
            ->postJson(
                "/api/contacts/{$contact->id}/consents",
                [
                    'channel' => 'whatsapp',
                    'status' => 'granted',
                    'source' => 'written_form',
                    'notes' => 'Signed campaign form',
                ]
            );

        $grantResponse
            ->assertOk()
            ->assertJsonPath(
                'data.consents.0.channel',
                'whatsapp'
            )
            ->assertJsonPath(
                'data.consents.0.status',
                'granted'
            )
            ->assertJsonPath(
                'data.consents.0.recorded_by.id',
                $admin->id
            );

        $consent = ContactConsent::query()
            ->where('contact_id', $contact->id)
            ->where('channel', 'whatsapp')
            ->firstOrFail();

        $this->assertNotNull($consent->consented_at);
        $this->assertNull($consent->revoked_at);

        $originalConsentedAt = $consent
            ->consented_at
            ->toDateTimeString();

        $this->postJson(
            "/api/contacts/{$contact->id}/consents",
            [
                'channel' => 'whatsapp',
                'status' => 'revoked',
                'source' => 'phone_request',
                'notes' => 'Contact requested opt-out',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.consents.0.status',
                'revoked'
            );

        $updatedConsent = $consent->fresh();

        $this->assertSame(
            $originalConsentedAt,
            $updatedConsent->consented_at->toDateTimeString()
        );
        $this->assertNotNull($updatedConsent->revoked_at);

        $this->assertDatabaseCount('contact_consents', 1);

        $this->postJson(
            "/api/contacts/{$contact->id}/consents",
            [
                'tenant_id' => $admin->tenant_id,
                'contact_id' => $contact->id,
                'recorded_by_user_id' => $admin->id,
                'consented_at' => now()->subYear()->toISOString(),
                'revoked_at' => now()->subMonth()->toISOString(),
                'channel' => 'email',
                'status' => 'granted',
                'source' => 'forged',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'tenant_id',
                'contact_id',
                'recorded_by_user_id',
                'consented_at',
                'revoked_at',
            ]);
    }

    public function test_coordinator_can_manage_contacts_and_consent_but_not_delete(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $coordinator = $this->createUserWithRole(
            $tenant,
            'coordinator'
        );

        $existingContact = $this->createContact(
            $tenant,
            'CEDRA-COORDINATOR'
        );

        $createResponse = $this->actingAs($coordinator)
            ->postJson('/api/contacts', [
                'reference_code' => 'CEDRA-COORDINATOR-NEW',
                'first_name' => 'Coordinator',
                'last_name' => 'Contact',
            ])
            ->assertCreated();

        $this->getJson('/api/contacts')
            ->assertOk();

        $this->patchJson(
            "/api/contacts/{$existingContact->id}",
            [
                'last_name' => 'Updated',
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.last_name', 'Updated');

        $this->postJson(
            "/api/contacts/{$existingContact->id}/consents",
            [
                'channel' => 'sms',
                'status' => 'denied',
                'source' => 'phone_call',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.consents.0.status',
                'denied'
            );

        $this->deleteJson(
            "/api/contacts/{$existingContact->id}"
        )
            ->assertForbidden();

        $this->assertDatabaseHas('contacts', [
            'id' => $existingContact->id,
        ]);

        $this->assertDatabaseHas('contacts', [
            'id' => $createResponse->json('data.id'),
            'created_by_user_id' => $coordinator->id,
        ]);
    }

    public function test_field_agent_cannot_access_contact_api(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $fieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $contact = $this->createContact(
            $tenant,
            'CEDRA-FIELD'
        );

        $this->actingAs($fieldAgent)
            ->getJson('/api/contacts')
            ->assertForbidden();

        $this->getJson("/api/contacts/{$contact->id}")
            ->assertForbidden();

        $this->postJson('/api/contacts', [
            'reference_code' => 'FORBIDDEN',
            'first_name' => 'Forbidden',
            'last_name' => 'Contact',
        ])
            ->assertForbidden();

        $this->patchJson(
            "/api/contacts/{$contact->id}",
            ['first_name' => 'Forbidden']
        )
            ->assertForbidden();

        $this->postJson(
            "/api/contacts/{$contact->id}/consents",
            [
                'channel' => 'sms',
                'status' => 'granted',
                'source' => 'forbidden',
            ]
        )
            ->assertForbidden();

        $this->deleteJson("/api/contacts/{$contact->id}")
            ->assertForbidden();
    }

    public function test_admin_cannot_access_another_tenants_contact(): void
    {
        $futureTenant = $this->findTenant('lebanon-future');

        $futureContact = $this->createContact(
            $futureTenant,
            'FUTURE-CONTACT'
        );

        $this->actingAs($this->cedraAdmin())
            ->getJson("/api/contacts/{$futureContact->id}")
            ->assertNotFound();

        $this->patchJson(
            "/api/contacts/{$futureContact->id}",
            ['first_name' => 'Forbidden']
        )
            ->assertNotFound();

        $this->postJson(
            "/api/contacts/{$futureContact->id}/consents",
            [
                'channel' => 'email',
                'status' => 'granted',
                'source' => 'forbidden',
            ]
        )
            ->assertNotFound();

        $this->deleteJson(
            "/api/contacts/{$futureContact->id}"
        )
            ->assertNotFound();
    }

    public function test_invalid_contact_filters_are_rejected(): void
    {
        $this->actingAs($this->cedraAdmin())
            ->getJson(
                '/api/contacts?status=deleted'
                .'&preferred_language=fr'
                .'&preferred_channel=telegram'
                .'&consent_channel=push'
                .'&consent_status=maybe'
                .'&per_page=500'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
                'preferred_language',
                'preferred_channel',
                'consent_channel',
                'consent_status',
                'per_page',
            ]);
    }

    private function cedraAdmin(): User
    {
        return User::query()
            ->where('email', 'admin@cedra.test')
            ->firstOrFail();
    }

    private function futureAdmin(): User
    {
        return User::query()
            ->where('email', 'admin@future.test')
            ->firstOrFail();
    }

    private function findTenant(string $slug): Tenant
    {
        return Tenant::query()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    private function findArea(Tenant $tenant): Area
    {
        return Area::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();
    }

    private function findRole(
        Tenant $tenant,
        string $roleSlug
    ): Role {
        return Role::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $roleSlug)
            ->firstOrFail();
    }

    private function createUserWithRole(
        Tenant $tenant,
        string $roleSlug
    ): User {
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $user->assignRole(
            $this->findRole($tenant, $roleSlug)
        );

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
}
