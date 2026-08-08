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
use Illuminate\Support\Facades\Gate;
use LogicException;
use Tests\TestCase;

class ContactFoundationTest extends TestCase
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

    public function test_contact_and_consent_relationships_work(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $tenant = $admin->tenant;
        $area = $this->findArea($tenant);

        $this->actingAs($admin);

        $contact = Contact::create([
            'area_id' => $area->id,
            'created_by_user_id' => $admin->id,
            'reference_code' => 'CEDRA-0001',
            'first_name' => 'Lina',
            'last_name' => 'Haddad',
            'phone' => '+96170111222',
            'email' => 'lina@example.test',
            'preferred_language' => 'ar',
            'preferred_channel' => 'whatsapp',
            'source' => 'field_registration',
        ]);

        $consent = ContactConsent::create([
            'contact_id' => $contact->id,
            'recorded_by_user_id' => $admin->id,
            'channel' => 'whatsapp',
            'status' => 'granted',
            'source' => 'verbal',
            'consented_at' => now(),
        ]);

        $this->assertSame($tenant->id, $contact->tenant_id);
        $this->assertSame($tenant->id, $consent->tenant_id);

        $this->assertTrue($contact->tenant->is($tenant));
        $this->assertTrue($contact->area->is($area));
        $this->assertTrue($contact->creator->is($admin));
        $this->assertTrue(
            $contact->consents()->firstOrFail()->is($consent)
        );

        $this->assertTrue($consent->contact->is($contact));
        $this->assertTrue($consent->recorder->is($admin));

        $this->assertTrue(
            $tenant->contacts()->firstOrFail()->is($contact)
        );
        $this->assertTrue(
            $tenant->contactConsents()->firstOrFail()->is($consent)
        );
        $this->assertTrue(
            $area->contacts()->firstOrFail()->is($contact)
        );
        $this->assertTrue(
            $admin->createdContacts()->firstOrFail()->is($contact)
        );
        $this->assertTrue(
            $admin->recordedContactConsents()
                ->firstOrFail()
                ->is($consent)
        );

        $this->assertNotNull($consent->consented_at);
    }

    public function test_tenant_can_only_query_its_own_contacts_and_consents(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $cedraContact = $this->createContact(
            $cedraAdmin,
            'SHARED-REFERENCE'
        );

        $this->actingAs($cedraAdmin);

        $cedraConsent = ContactConsent::create([
            'contact_id' => $cedraContact->id,
            'recorded_by_user_id' => $cedraAdmin->id,
            'channel' => 'sms',
            'status' => 'granted',
        ]);

        $futureContact = $this->createContact(
            $futureAdmin,
            'SHARED-REFERENCE'
        );

        $this->actingAs($futureAdmin);

        $futureConsent = ContactConsent::create([
            'contact_id' => $futureContact->id,
            'recorded_by_user_id' => $futureAdmin->id,
            'channel' => 'sms',
            'status' => 'denied',
        ]);

        $this->actingAs($cedraAdmin);

        $this->assertCount(1, Contact::all());
        $this->assertTrue(Contact::firstOrFail()->is($cedraContact));
        $this->assertNull(Contact::find($futureContact->id));

        $this->assertCount(1, ContactConsent::all());
        $this->assertTrue(
            ContactConsent::firstOrFail()->is($cedraConsent)
        );
        $this->assertNull(
            ContactConsent::find($futureConsent->id)
        );

        $this->assertSame(
            2,
            Contact::withoutGlobalScopes()->count()
        );
        $this->assertSame(
            2,
            ContactConsent::withoutGlobalScopes()->count()
        );
    }

    public function test_active_tenant_overrides_submitted_tenant_id(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $this->actingAs($cedraAdmin);

        $contact = Contact::create([
            'tenant_id' => $futureAdmin->tenant_id,
            'created_by_user_id' => $cedraAdmin->id,
            'reference_code' => 'CEDRA-OVERRIDE',
            'first_name' => 'Maya',
            'last_name' => 'Nassar',
        ]);

        $consent = ContactConsent::create([
            'tenant_id' => $futureAdmin->tenant_id,
            'contact_id' => $contact->id,
            'recorded_by_user_id' => $cedraAdmin->id,
            'channel' => 'email',
            'status' => 'granted',
        ]);

        $this->assertSame($cedraAdmin->tenant_id, $contact->tenant_id);
        $this->assertSame($cedraAdmin->tenant_id, $consent->tenant_id);
    }

    public function test_contact_cannot_use_another_tenants_area(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureTenant = $this->findTenant('lebanon-future');
        $futureArea = $this->findArea($futureTenant);

        $this->actingAs($cedraAdmin);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'The contact area must belong to the same tenant.'
        );

        Contact::create([
            'area_id' => $futureArea->id,
            'created_by_user_id' => $cedraAdmin->id,
            'reference_code' => 'INVALID-AREA',
            'first_name' => 'Invalid',
            'last_name' => 'Contact',
        ]);
    }

    public function test_contact_cannot_use_another_tenants_creator(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $this->actingAs($cedraAdmin);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'The contact creator must belong to the same tenant.'
        );

        Contact::create([
            'created_by_user_id' => $futureAdmin->id,
            'reference_code' => 'INVALID-CREATOR',
            'first_name' => 'Invalid',
            'last_name' => 'Creator',
        ]);
    }

    public function test_consent_cannot_use_cross_tenant_contact(): void
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
            'The consent contact must belong to the same tenant.'
        );

        ContactConsent::create([
            'contact_id' => $futureContact->id,
            'recorded_by_user_id' => $cedraAdmin->id,
            'channel' => 'phone',
            'status' => 'granted',
        ]);
    }

    public function test_contact_policy_enforces_roles_and_tenants(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureAdmin = $this->findUser('admin@future.test');
        $admin = $this->findUser('admin@cedra.test');

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
            'CEDRA-POLICY'
        );

        $otherContact = $this->createContact(
            $futureAdmin,
            'FUTURE-POLICY'
        );

        $this->actingAs($admin);

        $this->assertTrue(
            Gate::forUser($admin)->allows('viewAny', Contact::class)
        );
        $this->assertTrue(
            Gate::forUser($admin)->allows('view', $ownContact)
        );
        $this->assertTrue(
            Gate::forUser($admin)->allows('create', Contact::class)
        );
        $this->assertTrue(
            Gate::forUser($admin)->allows('update', $ownContact)
        );
        $this->assertTrue(
            Gate::forUser($admin)->allows('delete', $ownContact)
        );
        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'manageConsent',
                $ownContact
            )
        );
        $this->assertTrue(
            Gate::forUser($admin)->allows('import', Contact::class)
        );
        $this->assertTrue(
            Gate::forUser($admin)->allows('export', Contact::class)
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'viewAny',
                Contact::class
            )
        );
        $this->assertTrue(
            Gate::forUser($coordinator)->allows('view', $ownContact)
        );
        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'create',
                Contact::class
            )
        );
        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'update',
                $ownContact
            )
        );
        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'manageConsent',
                $ownContact
            )
        );
        $this->assertFalse(
            Gate::forUser($coordinator)->allows(
                'delete',
                $ownContact
            )
        );
        $this->assertFalse(
            Gate::forUser($coordinator)->allows(
                'import',
                Contact::class
            )
        );
        $this->assertFalse(
            Gate::forUser($coordinator)->allows(
                'export',
                Contact::class
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'viewAny',
                Contact::class
            )
        );
        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'view',
                $ownContact
            )
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows('view', $otherContact)
        );
        $this->assertFalse(
            Gate::forUser($admin)->allows('update', $otherContact)
        );
        $this->assertFalse(
            Gate::forUser($admin)->allows('delete', $otherContact)
        );
        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'manageConsent',
                $otherContact
            )
        );
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
            'phone' => '+96170000000',
            'preferred_language' => 'en',
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

    private function findArea(Tenant $tenant): Area
    {
        return Area::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();
    }
}
