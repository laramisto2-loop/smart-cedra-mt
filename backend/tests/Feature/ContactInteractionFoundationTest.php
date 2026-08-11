<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactInteraction;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Tests\TestCase;

class ContactInteractionFoundationTest extends TestCase
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

    public function test_interaction_relationships_work(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $tenant = $admin->tenant;
        $contact = $this->createContact(
            $admin,
            'INTERACTION-CONTACT'
        );

        $this->actingAs($admin);

        $interaction = ContactInteraction::create([
            'contact_id' => $contact->id,
            'recorded_by_user_id' => $admin->id,
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'outcome' => 'completed',
            'subject' => 'Volunteer follow-up',
            'notes' => 'Confirmed availability.',
            'duration_seconds' => 180,
            'occurred_at' => now(),
            'consent_status_snapshot' => 'granted',
            'consent_checked_at' => now(),
        ]);

        $this->assertSame($tenant->id, $interaction->tenant_id);
        $this->assertTrue($interaction->tenant->is($tenant));
        $this->assertTrue($interaction->contact->is($contact));
        $this->assertTrue($interaction->recorder->is($admin));

        $this->assertTrue(
            $contact->interactions()
                ->firstOrFail()
                ->is($interaction)
        );

        $this->assertTrue(
            $tenant->contactInteractions()
                ->firstOrFail()
                ->is($interaction)
        );

        $this->assertTrue(
            $admin->recordedContactInteractions()
                ->firstOrFail()
                ->is($interaction)
        );

        $this->assertSame(180, $interaction->duration_seconds);
        $this->assertNotNull($interaction->occurred_at);
        $this->assertNotNull($interaction->consent_checked_at);
    }

    public function test_tenant_only_queries_its_own_interactions(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $cedraContact = $this->createContact(
            $cedraAdmin,
            'CEDRA-INTERACTION'
        );

        $futureContact = $this->createContact(
            $futureAdmin,
            'FUTURE-INTERACTION'
        );

        $cedraInteraction = $this->createInteraction(
            $cedraAdmin,
            $cedraContact
        );

        $futureInteraction = $this->createInteraction(
            $futureAdmin,
            $futureContact
        );

        $this->actingAs($cedraAdmin);

        $this->assertCount(1, ContactInteraction::all());

        $this->assertTrue(
            ContactInteraction::firstOrFail()
                ->is($cedraInteraction)
        );

        $this->assertNull(
            ContactInteraction::find($futureInteraction->id)
        );

        $this->assertSame(
            2,
            ContactInteraction::withoutGlobalScopes()->count()
        );
    }

    public function test_active_tenant_overrides_submitted_tenant_id(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $contact = $this->createContact(
            $cedraAdmin,
            'TENANT-OVERRIDE-CONTACT'
        );

        $this->actingAs($cedraAdmin);

        $interaction = ContactInteraction::create([
            'tenant_id' => $futureAdmin->tenant_id,
            'contact_id' => $contact->id,
            'recorded_by_user_id' => $cedraAdmin->id,
            'channel' => 'note',
            'direction' => 'internal',
            'outcome' => 'informational',
            'occurred_at' => now(),
        ]);

        $this->assertSame(
            $cedraAdmin->tenant_id,
            $interaction->tenant_id
        );
    }

    public function test_interaction_rejects_cross_tenant_contact(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $futureContact = $this->createContact(
            $futureAdmin,
            'FUTURE-CONTACT'
        );

        $this->actingAs($cedraAdmin);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'The interaction contact must belong to the same tenant.'
        );

        ContactInteraction::create([
            'contact_id' => $futureContact->id,
            'recorded_by_user_id' => $cedraAdmin->id,
            'channel' => 'phone',
            'direction' => 'outbound',
            'occurred_at' => now(),
        ]);
    }

    public function test_interaction_rejects_cross_tenant_recorder(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $contact = $this->createContact(
            $cedraAdmin,
            'CEDRA-CONTACT'
        );

        $this->actingAs($cedraAdmin);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'The interaction recorder must belong to the same tenant.'
        );

        ContactInteraction::create([
            'contact_id' => $contact->id,
            'recorded_by_user_id' => $futureAdmin->id,
            'channel' => 'email',
            'direction' => 'outbound',
            'occurred_at' => now(),
        ]);
    }

    public function test_interaction_policy_enforces_roles_and_tenants(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $admin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $coordinator = $this->createUserWithRole(
            $cedraTenant,
            'coordinator'
        );

        $fieldAgent = $this->createUserWithRole(
            $cedraTenant,
            'field_agent'
        );

        $ownContact = $this->createContact(
            $admin,
            'OWN-POLICY-CONTACT'
        );

        $otherContact = $this->createContact(
            $futureAdmin,
            'OTHER-POLICY-CONTACT'
        );

        $ownInteraction = $this->createInteraction(
            $admin,
            $ownContact
        );

        $otherInteraction = $this->createInteraction(
            $futureAdmin,
            $otherContact
        );

        $this->actingAs($admin);

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'viewAny',
                ContactInteraction::class
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'view',
                $ownInteraction
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'create',
                ContactInteraction::class
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'update',
                $ownInteraction
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'delete',
                $ownInteraction
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'viewAny',
                ContactInteraction::class
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'view',
                $ownInteraction
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'create',
                ContactInteraction::class
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'update',
                $ownInteraction
            )
        );

        $this->assertFalse(
            Gate::forUser($coordinator)->allows(
                'delete',
                $ownInteraction
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'viewAny',
                ContactInteraction::class
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'view',
                $ownInteraction
            )
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'view',
                $otherInteraction
            )
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'update',
                $otherInteraction
            )
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'delete',
                $otherInteraction
            )
        );
    }

    private function createInteraction(
        User $user,
        Contact $contact
    ): ContactInteraction {
        $this->actingAs($user);

        return ContactInteraction::create([
            'contact_id' => $contact->id,
            'recorded_by_user_id' => $user->id,
            'channel' => 'phone',
            'direction' => 'outbound',
            'outcome' => 'completed',
            'subject' => 'Foundation test interaction',
            'occurred_at' => now(),
        ]);
    }

    private function createContact(
        User $user,
        string $referenceCode
    ): Contact {
        $this->actingAs($user);

        return Contact::create([
            'created_by_user_id' => $user->id,
            'reference_code' => $referenceCode,
            'first_name' => 'Test',
            'last_name' => 'Contact',
            'status' => 'active',
        ]);
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
