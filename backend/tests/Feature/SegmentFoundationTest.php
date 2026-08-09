<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactSegment;
use App\Models\Role;
use App\Models\Segment;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Tests\TestCase;

class SegmentFoundationTest extends TestCase
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

    public function test_segment_membership_relationships_work(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $tenant = $admin->tenant;

        $segment = $this->createSegment(
            $admin,
            'VOLUNTEERS'
        );

        $contact = $this->createContact(
            $admin,
            'SEGMENT-CONTACT'
        );

        $this->actingAs($admin);

        $membership = ContactSegment::create([
            'contact_id' => $contact->id,
            'segment_id' => $segment->id,
            'added_by_user_id' => $admin->id,
            'added_at' => now(),
        ]);

        $this->assertSame($tenant->id, $segment->tenant_id);
        $this->assertSame($tenant->id, $membership->tenant_id);

        $this->assertTrue($segment->tenant->is($tenant));
        $this->assertTrue($segment->creator->is($admin));
        $this->assertTrue($membership->contact->is($contact));
        $this->assertTrue($membership->segment->is($segment));
        $this->assertTrue($membership->addedBy->is($admin));

        $this->assertTrue(
            $segment->contacts()->firstOrFail()->is($contact)
        );
        $this->assertTrue(
            $contact->segments()->firstOrFail()->is($segment)
        );
        $this->assertTrue(
            $tenant->segments()->firstOrFail()->is($segment)
        );
        $this->assertTrue(
            $tenant->contactSegmentMemberships()
                ->firstOrFail()
                ->is($membership)
        );
        $this->assertTrue(
            $admin->createdSegments()
                ->firstOrFail()
                ->is($segment)
        );
        $this->assertTrue(
            $admin->addedSegmentMemberships()
                ->firstOrFail()
                ->is($membership)
        );
    }

    public function test_tenant_only_queries_its_own_segments(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $cedraSegment = $this->createSegment(
            $cedraAdmin,
            'SHARED-CODE'
        );

        $futureSegment = $this->createSegment(
            $futureAdmin,
            'SHARED-CODE'
        );

        $this->actingAs($cedraAdmin);

        $this->assertCount(1, Segment::all());
        $this->assertTrue(
            Segment::firstOrFail()->is($cedraSegment)
        );
        $this->assertNull(Segment::find($futureSegment->id));

        $this->assertSame(
            2,
            Segment::withoutGlobalScopes()->count()
        );
    }

    public function test_active_tenant_overrides_submitted_tenant_id(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $this->actingAs($cedraAdmin);

        $segment = Segment::create([
            'tenant_id' => $futureAdmin->tenant_id,
            'created_by_user_id' => $cedraAdmin->id,
            'code' => 'TENANT-OVERRIDE',
            'name' => 'Tenant Override',
            'type' => 'static',
        ]);

        $this->assertSame(
            $cedraAdmin->tenant_id,
            $segment->tenant_id
        );
    }

    public function test_segment_rejects_cross_tenant_creator(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $this->actingAs($cedraAdmin);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'The segment creator must belong to the same tenant.'
        );

        Segment::create([
            'created_by_user_id' => $futureAdmin->id,
            'code' => 'INVALID-CREATOR',
            'name' => 'Invalid Creator',
            'type' => 'static',
        ]);
    }

    public function test_membership_rejects_cross_tenant_contact(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $segment = $this->createSegment(
            $cedraAdmin,
            'CEDRA-SEGMENT'
        );

        $futureContact = $this->createContact(
            $futureAdmin,
            'FUTURE-CONTACT'
        );

        $this->actingAs($cedraAdmin);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'The segment contact must belong to the same tenant.'
        );

        ContactSegment::create([
            'contact_id' => $futureContact->id,
            'segment_id' => $segment->id,
            'added_by_user_id' => $cedraAdmin->id,
        ]);
    }

    public function test_dynamic_segment_rejects_manual_membership(): void
    {
        $admin = $this->findUser('admin@cedra.test');

        $segment = $this->createSegment(
            $admin,
            'DYNAMIC-SEGMENT',
            'dynamic'
        );

        $contact = $this->createContact(
            $admin,
            'DYNAMIC-CONTACT'
        );

        $this->actingAs($admin);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'Only static segments accept manual memberships.'
        );

        ContactSegment::create([
            'contact_id' => $contact->id,
            'segment_id' => $segment->id,
            'added_by_user_id' => $admin->id,
        ]);
    }

    public function test_segment_policy_enforces_roles_and_tenants(): void
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

        $staticSegment = $this->createSegment(
            $admin,
            'STATIC-POLICY'
        );

        $dynamicSegment = $this->createSegment(
            $admin,
            'DYNAMIC-POLICY',
            'dynamic'
        );

        $otherSegment = $this->createSegment(
            $futureAdmin,
            'OTHER-POLICY'
        );

        $this->actingAs($admin);

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'viewAny',
                Segment::class
            )
        );
        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'view',
                $staticSegment
            )
        );
        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'create',
                Segment::class
            )
        );
        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'update',
                $staticSegment
            )
        );
        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'delete',
                $staticSegment
            )
        );
        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'manageMembers',
                $staticSegment
            )
        );
        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'manageMembers',
                $dynamicSegment
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'viewAny',
                Segment::class
            )
        );
        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'create',
                Segment::class
            )
        );
        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'update',
                $staticSegment
            )
        );
        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'manageMembers',
                $staticSegment
            )
        );
        $this->assertFalse(
            Gate::forUser($coordinator)->allows(
                'delete',
                $staticSegment
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'viewAny',
                Segment::class
            )
        );
        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'view',
                $staticSegment
            )
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'view',
                $otherSegment
            )
        );
        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'update',
                $otherSegment
            )
        );
        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'delete',
                $otherSegment
            )
        );
        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'manageMembers',
                $otherSegment
            )
        );
    }

    private function createSegment(
        User $user,
        string $code,
        string $type = 'static'
    ): Segment {
        $this->actingAs($user);

        return Segment::create([
            'created_by_user_id' => $user->id,
            'code' => $code,
            'name' => str_replace('-', ' ', $code),
            'type' => $type,
            'criteria' => $type === 'dynamic'
                ? ['status' => 'active']
                : null,
            'status' => 'active',
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
