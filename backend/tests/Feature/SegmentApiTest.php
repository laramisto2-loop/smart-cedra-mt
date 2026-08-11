<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Contact;
use App\Models\ContactConsent;
use App\Models\Role;
use App\Models\Segment;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\GeographySeeder;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SegmentApiTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_segment_api(): void
    {
        $this->getJson('/api/segments')
            ->assertUnauthorized();

        $this->postJson('/api/segments', [])
            ->assertUnauthorized();

        $this->getJson('/api/segments/1/members')
            ->assertUnauthorized();

        $this->putJson('/api/segments/1/members', [
            'contact_ids' => [],
        ])
            ->assertUnauthorized();
    }

    public function test_admin_only_receives_searches_and_filters_own_segments(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');

        $this->createSegment(
            $cedraTenant,
            'CEDRA-ACTIVE',
            [
                'name' => 'Active supporters',
                'type' => 'static',
                'status' => 'active',
            ]
        );

        $this->createSegment(
            $cedraTenant,
            'CEDRA-DYNAMIC',
            [
                'name' => 'Arabic contacts',
                'type' => 'dynamic',
                'criteria' => [
                    'preferred_language' => 'ar',
                ],
                'status' => 'archived',
            ]
        );

        $this->createSegment(
            $futureTenant,
            'FUTURE-SEGMENT',
            [
                'name' => 'Future supporters',
            ]
        );

        $this->actingAs($this->cedraAdmin())
            ->getJson('/api/segments')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing([
                'code' => 'FUTURE-SEGMENT',
            ]);

        $this->getJson('/api/segments?search=Arabic')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'CEDRA-DYNAMIC');

        $this->getJson('/api/segments?type=static&status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'CEDRA-ACTIVE');
    }

    public function test_tenant_admin_can_create_update_view_and_delete_segment(): void
    {
        $admin = $this->cedraAdmin();

        $response = $this->actingAs($admin)
            ->postJson('/api/segments', [
                'code' => '  volunteers-2026 ',
                'name' => 'Campaign Volunteers',
                'description' => 'Available campaign volunteers.',
                'type' => 'static',
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'VOLUNTEERS-2026')
            ->assertJsonPath('data.name', 'Campaign Volunteers')
            ->assertJsonPath('data.type', 'static')
            ->assertJsonPath('data.member_count', 0)
            ->assertJsonPath('data.creator.id', $admin->id);

        $segmentId = $response->json('data.id');

        $this->assertDatabaseHas('segments', [
            'id' => $segmentId,
            'tenant_id' => $admin->tenant_id,
            'created_by_user_id' => $admin->id,
            'code' => 'VOLUNTEERS-2026',
        ]);

        $this->patchJson("/api/segments/{$segmentId}", [
            'name' => 'Archived Volunteers',
            'status' => 'archived',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Archived Volunteers')
            ->assertJsonPath('data.status', 'archived');

        $this->getJson("/api/segments/{$segmentId}")
            ->assertOk()
            ->assertJsonPath('data.id', $segmentId);

        $this->deleteJson("/api/segments/{$segmentId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('segments', [
            'id' => $segmentId,
        ]);
    }

    public function test_segment_validation_protects_tenant_and_rules(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');

        $existing = $this->createSegment(
            $cedraTenant,
            'EXISTING',
            ['name' => 'Existing segment']
        );

        $futureArea = Area::withoutGlobalScopes()
            ->where('tenant_id', $futureTenant->id)
            ->firstOrFail();

        $this->actingAs($this->cedraAdmin())
            ->postJson('/api/segments', [
                'tenant_id' => $futureTenant->id,
                'created_by_user_id' => $this->futureAdmin()->id,
                'code' => 'invalid code',
                'name' => '',
                'type' => 'static',
                'criteria' => [
                    'contact_status' => 'active',
                ],
                'status' => 'deleted',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'tenant_id',
                'created_by_user_id',
                'code',
                'name',
                'criteria',
                'status',
            ]);

        $this->postJson('/api/segments', [
            'code' => $existing->code,
            'name' => 'Duplicate',
            'type' => 'static',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->postJson('/api/segments', [
            'code' => 'DYNAMIC-WITHOUT-RULES',
            'name' => 'Missing rules',
            'type' => 'dynamic',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('criteria');

        $this->postJson('/api/segments', [
            'code' => 'CROSS-TENANT-AREA',
            'name' => 'Invalid area',
            'type' => 'dynamic',
            'criteria' => [
                'area_id' => $futureArea->id,
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('criteria.area_id');
    }

    public function test_admin_can_safely_sync_static_segment_members(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');
        $admin = $this->cedraAdmin();

        $segment = $this->createSegment(
            $cedraTenant,
            'STATIC-MEMBERS',
            ['name' => 'Static members']
        );

        $first = $this->createContact(
            $cedraTenant,
            'CEDRA-MEMBER-1',
            ['first_name' => 'Maya']
        );

        $second = $this->createContact(
            $cedraTenant,
            'CEDRA-MEMBER-2',
            ['first_name' => 'Nadim']
        );

        $futureContact = $this->createContact(
            $futureTenant,
            'FUTURE-MEMBER'
        );

        $this->actingAs($admin)
            ->putJson(
                "/api/segments/{$segment->id}/members",
                [
                    'tenant_id' => $futureTenant->id,
                    'added_by_user_id' => $this->futureAdmin()->id,
                    'contact_ids' => [$first->id],
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'tenant_id',
                'added_by_user_id',
            ]);

        $this->putJson(
            "/api/segments/{$segment->id}/members",
            [
                'contact_ids' => [$futureContact->id],
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact_ids.0');

        $this->putJson(
            "/api/segments/{$segment->id}/members",
            [
                'contact_ids' => [
                    $first->id,
                    $second->id,
                ],
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.member_count', 2);

        $this->assertDatabaseHas('contact_segment', [
            'tenant_id' => $cedraTenant->id,
            'segment_id' => $segment->id,
            'contact_id' => $first->id,
            'added_by_user_id' => $admin->id,
        ]);

        $this->getJson("/api/segments/{$segment->id}/members")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing([
                'reference_code' => 'FUTURE-MEMBER',
            ]);

        $this->putJson(
            "/api/segments/{$segment->id}/members",
            [
                'contact_ids' => [$second->id],
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.member_count', 1);

        $this->assertDatabaseMissing('contact_segment', [
            'segment_id' => $segment->id,
            'contact_id' => $first->id,
        ]);
    }

    public function test_dynamic_segment_resolves_matching_contacts(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $matching = $this->createContact(
            $tenant,
            'MATCHING-CONTACT',
            [
                'first_name' => 'Maya',
                'preferred_language' => 'ar',
                'preferred_channel' => 'whatsapp',
                'status' => 'active',
            ]
        );

        $this->createConsent(
            $tenant,
            $matching,
            'whatsapp',
            'granted'
        );

        $wrongLanguage = $this->createContact(
            $tenant,
            'WRONG-LANGUAGE',
            [
                'preferred_language' => 'en',
                'preferred_channel' => 'whatsapp',
                'status' => 'active',
            ]
        );

        $this->createConsent(
            $tenant,
            $wrongLanguage,
            'whatsapp',
            'granted'
        );

        $inactive = $this->createContact(
            $tenant,
            'INACTIVE-CONTACT',
            [
                'preferred_language' => 'ar',
                'preferred_channel' => 'whatsapp',
                'status' => 'inactive',
            ]
        );

        $this->createConsent(
            $tenant,
            $inactive,
            'whatsapp',
            'granted'
        );

        $response = $this->actingAs($this->cedraAdmin())
            ->postJson('/api/segments', [
                'code' => 'AR-WHATSAPP',
                'name' => 'Arabic WhatsApp contacts',
                'type' => 'dynamic',
                'criteria' => [
                    'contact_status' => 'active',
                    'preferred_language' => 'ar',
                    'preferred_channel' => 'whatsapp',
                    'consent_channel' => 'whatsapp',
                    'consent_status' => 'granted',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.member_count', 1);

        $segmentId = $response->json('data.id');

        $this->getJson("/api/segments/{$segmentId}/members")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.reference_code',
                'MATCHING-CONTACT'
            );

        $this->putJson(
            "/api/segments/{$segmentId}/members",
            [
                'contact_ids' => [$wrongLanguage->id],
            ]
        )
            ->assertForbidden();

        $this->assertDatabaseMissing('contact_segment', [
            'segment_id' => $segmentId,
        ]);
    }

    public function test_coordinator_can_manage_static_segments_but_cannot_delete(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $coordinator = $this->createUserWithRole(
            $tenant,
            'coordinator'
        );

        $contact = $this->createContact(
            $tenant,
            'COORDINATOR-MEMBER'
        );

        $response = $this->actingAs($coordinator)
            ->postJson('/api/segments', [
                'code' => 'COORDINATOR-SEGMENT',
                'name' => 'Coordinator segment',
                'type' => 'static',
            ])
            ->assertCreated();

        $segmentId = $response->json('data.id');

        $this->putJson(
            "/api/segments/{$segmentId}/members",
            ['contact_ids' => [$contact->id]]
        )
            ->assertOk()
            ->assertJsonPath('data.member_count', 1);

        $this->patchJson("/api/segments/{$segmentId}", [
            'name' => 'Updated by coordinator',
        ])
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Updated by coordinator'
            );

        $this->deleteJson("/api/segments/{$segmentId}")
            ->assertForbidden();

        $this->assertDatabaseHas('segments', [
            'id' => $segmentId,
        ]);
    }

    public function test_field_agent_cannot_access_segment_api(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $fieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $segment = $this->createSegment(
            $tenant,
            'FIELD-AGENT-SEGMENT'
        );

        $this->actingAs($fieldAgent)
            ->getJson('/api/segments')
            ->assertForbidden();

        $this->getJson("/api/segments/{$segment->id}")
            ->assertForbidden();

        $this->postJson('/api/segments', [
            'code' => 'FORBIDDEN',
            'name' => 'Forbidden',
        ])
            ->assertForbidden();

        $this->putJson(
            "/api/segments/{$segment->id}/members",
            ['contact_ids' => []]
        )
            ->assertForbidden();
    }

    public function test_admin_cannot_access_another_tenants_segment(): void
    {
        $futureTenant = $this->findTenant('lebanon-future');

        $segment = $this->createSegment(
            $futureTenant,
            'FUTURE-ONLY'
        );

        $this->actingAs($this->cedraAdmin())
            ->getJson("/api/segments/{$segment->id}")
            ->assertNotFound();

        $this->patchJson(
            "/api/segments/{$segment->id}",
            ['name' => 'Forbidden']
        )
            ->assertNotFound();

        $this->getJson(
            "/api/segments/{$segment->id}/members"
        )
            ->assertNotFound();

        $this->putJson(
            "/api/segments/{$segment->id}/members",
            ['contact_ids' => []]
        )
            ->assertNotFound();

        $this->deleteJson("/api/segments/{$segment->id}")
            ->assertNotFound();
    }

    public function test_invalid_segment_filters_are_rejected(): void
    {
        $this->actingAs($this->cedraAdmin())
            ->getJson(
                '/api/segments?type=automatic'
                .'&status=deleted'
                .'&per_page=500'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'type',
                'status',
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
    private function createSegment(
        Tenant $tenant,
        string $code,
        array $attributes = []
    ): Segment {
        return Segment::withoutGlobalScopes()->create(
            array_merge(
                [
                    'name' => 'Test segment',
                    'type' => 'static',
                    'status' => 'active',
                ],
                $attributes,
                [
                    'tenant_id' => $tenant->id,
                    'code' => $code,
                ]
            )
        );
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
