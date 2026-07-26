<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class RbacAuthorizationTest extends TestCase
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

    public function test_user_can_receive_a_role_from_their_own_tenant(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $coordinatorRole = $this->findRole($tenant, 'coordinator');

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $user->assignRole($coordinatorRole);

        $this->assertTrue($user->fresh()->hasRole('coordinator'));

        $this->assertDatabaseHas('role_user', [
            'role_id' => $coordinatorRole->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_user_cannot_receive_a_role_from_another_tenant(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');

        $cedraUser = User::factory()->create([
            'tenant_id' => $cedraTenant->id,
        ]);

        $futureCoordinatorRole = $this->findRole(
            $futureTenant,
            'coordinator'
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'A user may only receive roles belonging to their own tenant.'
        );

        $cedraUser->assignRole($futureCoordinatorRole);
    }

    public function test_tenant_admin_has_all_seeded_permissions(): void
    {
        $admin = User::query()
            ->where('email', 'admin@cedra.test')
            ->firstOrFail();

        $expectedPermissions = [
            'roles.manage',
            'users.manage',
            'geography.view',
            'geography.create',
            'geography.update',
            'geography.delete',
            'audit.view',
        ];

        $this->assertTrue($admin->hasRole('tenant_admin'));

        foreach ($expectedPermissions as $permission) {
            $this->assertTrue(
                $admin->hasPermission($permission),
                "Tenant Admin should have the {$permission} permission."
            );
        }
    }

    public function test_coordinator_has_only_coordinator_permissions(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $coordinatorRole = $this->findRole($tenant, 'coordinator');

        $coordinator = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $coordinator->assignRole($coordinatorRole);

        $this->assertTrue(
            $coordinator->hasPermission('geography.view')
        );
        $this->assertTrue(
            $coordinator->hasPermission('geography.create')
        );
        $this->assertTrue(
            $coordinator->hasPermission('geography.update')
        );

        $this->assertFalse(
            $coordinator->hasPermission('geography.delete')
        );
        $this->assertFalse(
            $coordinator->hasPermission('roles.manage')
        );
        $this->assertFalse(
            $coordinator->hasPermission('users.manage')
        );
        $this->assertFalse(
            $coordinator->hasPermission('audit.view')
        );
    }

    public function test_field_agent_only_has_geography_view_permission(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $fieldAgentRole = $this->findRole($tenant, 'field_agent');

        $fieldAgent = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $fieldAgent->assignRole($fieldAgentRole);

        $this->assertTrue(
            $fieldAgent->hasRole('field_agent')
        );
        $this->assertTrue(
            $fieldAgent->hasPermission('geography.view')
        );

        $this->assertFalse(
            $fieldAgent->hasPermission('geography.create')
        );
        $this->assertFalse(
            $fieldAgent->hasPermission('geography.update')
        );
        $this->assertFalse(
            $fieldAgent->hasPermission('geography.delete')
        );
        $this->assertFalse(
            $fieldAgent->hasPermission('roles.manage')
        );
        $this->assertFalse(
            $fieldAgent->hasPermission('audit.view')
        );
    }

    private function findTenant(string $slug): Tenant
    {
        return Tenant::query()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    private function findRole(Tenant $tenant, string $slug): Role
    {
        return Role::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->firstOrFail();
    }
}
