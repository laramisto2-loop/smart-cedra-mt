<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserRoleManagementApiTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_user_and_role_management_api(): void
    {
        $this->getJson('/api/users')
            ->assertUnauthorized();

        $this->postJson('/api/users', [])
            ->assertUnauthorized();

        $this->getJson('/api/roles')
            ->assertUnauthorized();

        $this->postJson('/api/roles', [])
            ->assertUnauthorized();

        $this->getJson('/api/roles/permissions')
            ->assertUnauthorized();
    }

    public function test_user_without_management_permissions_is_forbidden(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $coordinator = $this->createUserWithRole(
            $tenant,
            'coordinator'
        );

        $this->actingAs($coordinator)
            ->getJson('/api/users')
            ->assertForbidden();

        $this->actingAs($coordinator)
            ->postJson('/api/users', [])
            ->assertForbidden();

        $this->actingAs($coordinator)
            ->getJson('/api/roles')
            ->assertForbidden();

        $this->actingAs($coordinator)
            ->postJson('/api/roles', [])
            ->assertForbidden();

        $this->actingAs($coordinator)
            ->getJson('/api/roles/permissions')
            ->assertForbidden();
    }

    public function test_admin_manages_users_and_only_sees_own_tenant(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');
        $coordinatorRole = $this->findRole(
            $admin->tenant,
            'coordinator'
        );

        $response = $this->actingAs($admin)
            ->postJson('/api/users', [
                'name' => 'Campaign Coordinator',
                'email' => 'new.coordinator@cedra.test',
                'password' => 'SecurePassword123!',
                'password_confirmation' => 'SecurePassword123!',
                'role_ids' => [
                    $coordinatorRole->id,
                ],
            ])
            ->assertCreated()
            ->assertJsonPath(
                'data.name',
                'Campaign Coordinator'
            )
            ->assertJsonPath(
                'data.email',
                'new.coordinator@cedra.test'
            )
            ->assertJsonPath(
                'data.roles.0.slug',
                'coordinator'
            )
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.tenant_id');

        $userId = $response->json('data.id');

        $createdUser = User::query()->findOrFail($userId);

        $this->assertSame(
            $admin->tenant_id,
            $createdUser->tenant_id
        );
        $this->assertTrue(
            Hash::check(
                'SecurePassword123!',
                $createdUser->password
            )
        );
        $this->assertTrue(
            $createdUser->hasRole('coordinator')
        );

        $listResponse = $this->actingAs($admin)
            ->getJson(
                '/api/users?search=Campaign'
                .'&role_id='.$coordinatorRole->id
            )
            ->assertOk();

        $listedUserIds = collect(
            $listResponse->json('data')
        )->pluck('id');

        $this->assertTrue(
            $listedUserIds->contains($createdUser->id)
        );
        $this->assertFalse(
            $listedUserIds->contains($futureAdmin->id)
        );

        $this->actingAs($admin)
            ->getJson('/api/users/'.$createdUser->id)
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $createdUser->id
            )
            ->assertJsonPath(
                'data.is_current_user',
                false
            );

        $this->actingAs($admin)
            ->patchJson('/api/users/'.$createdUser->id, [
                'name' => 'Updated Coordinator',
                'email' => 'updated.coordinator@cedra.test',
            ])
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Updated Coordinator'
            )
            ->assertJsonPath(
                'data.email',
                'updated.coordinator@cedra.test'
            );

        $this->assertDatabaseHas('users', [
            'id' => $createdUser->id,
            'tenant_id' => $admin->tenant_id,
            'name' => 'Updated Coordinator',
            'email' => 'updated.coordinator@cedra.test',
        ]);

        $this->actingAs($admin)
            ->getJson('/api/users/'.$futureAdmin->id)
            ->assertForbidden();

        $this->actingAs($admin)
            ->patchJson('/api/users/'.$futureAdmin->id, [
                'name' => 'Forbidden Update',
            ])
            ->assertForbidden();
    }

    public function test_admin_synchronizes_user_roles_and_deletes_safe_users(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $admin = $this->findUser('admin@cedra.test');

        $coordinatorRole = $this->findRole(
            $tenant,
            'coordinator'
        );
        $fieldAgentRole = $this->findRole(
            $tenant,
            'field_agent'
        );

        $managedUser = $this->createUserWithRole(
            $tenant,
            'coordinator'
        );

        $this->actingAs($admin)
            ->patchJson(
                '/api/users/'.$managedUser->id.'/roles',
                [
                    'role_ids' => [
                        $fieldAgentRole->id,
                    ],
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.roles.0.slug',
                'field_agent'
            );

        $managedUser->refresh();

        $this->assertFalse(
            $managedUser->hasRole('coordinator')
        );
        $this->assertTrue(
            $managedUser->hasRole('field_agent')
        );

        $this->actingAs($admin)
            ->deleteJson('/api/users/'.$managedUser->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('users', [
            'id' => $managedUser->id,
        ]);

        $otherTenantRole = $this->findRole(
            $this->findTenant('lebanon-future'),
            'coordinator'
        );

        $anotherUser = $this->createUserWithRole(
            $tenant,
            'coordinator'
        );

        $this->actingAs($admin)
            ->patchJson(
                '/api/users/'.$anotherUser->id.'/roles',
                [
                    'role_ids' => [
                        $otherTenantRole->id,
                    ],
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role_ids.0');

        $this->assertTrue(
            $anotherUser->fresh()->hasRole(
                $coordinatorRole->slug
            )
        );
    }

    public function test_self_deletion_and_removing_the_final_administrator_are_prevented(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $coordinatorRole = $this->findRole(
            $admin->tenant,
            'coordinator'
        );

        $this->actingAs($admin)
            ->deleteJson('/api/users/'.$admin->id)
            ->assertForbidden();

        $this->actingAs($admin)
            ->patchJson(
                '/api/users/'.$admin->id.'/roles',
                [
                    'role_ids' => [
                        $coordinatorRole->id,
                    ],
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role_ids');

        $this->assertTrue(
            $admin->fresh()->hasRole('tenant_admin')
        );
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
        ]);
    }

    public function test_admin_manages_custom_roles_and_permissions(): void
    {
        $admin = $this->findUser('admin@cedra.test');

        $viewContacts = $this->findPermission(
            'contacts.view'
        );
        $viewTasks = $this->findPermission(
            'tasks.view'
        );

        $this->actingAs($admin)
            ->getJson('/api/roles/permissions')
            ->assertOk()
            ->assertJsonFragment([
                'slug' => 'contacts.view',
            ])
            ->assertJsonFragment([
                'slug' => 'tasks.view',
            ]);

        $response = $this->actingAs($admin)
            ->postJson('/api/roles', [
                'name' => 'Contact Reviewer',
                'slug' => 'contact_reviewer',
                'description' => (
                    'Reviews tenant contacts without editing them.'
                ),
                'permission_ids' => [
                    $viewContacts->id,
                ],
            ])
            ->assertCreated()
            ->assertJsonPath(
                'data.name',
                'Contact Reviewer'
            )
            ->assertJsonPath(
                'data.slug',
                'contact_reviewer'
            )
            ->assertJsonPath(
                'data.permissions.0.slug',
                'contacts.view'
            );

        $roleId = $response->json('data.id');

        $role = Role::withoutGlobalScopes()
            ->findOrFail($roleId);

        $this->assertSame(
            $admin->tenant_id,
            $role->tenant_id
        );
        $this->assertTrue(
            $role->hasPermission('contacts.view')
        );

        $this->actingAs($admin)
            ->patchJson('/api/roles/'.$role->id, [
                'name' => 'Campaign Reviewer',
                'slug' => 'campaign_reviewer',
                'description' => 'Reviews contacts and tasks.',
            ])
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Campaign Reviewer'
            )
            ->assertJsonPath(
                'data.slug',
                'campaign_reviewer'
            );

        $this->actingAs($admin)
            ->patchJson(
                '/api/roles/'.$role->id.'/permissions',
                [
                    'permission_ids' => [
                        $viewContacts->id,
                        $viewTasks->id,
                    ],
                ]
            )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.permissions'
            );

        $role->refresh();

        $this->assertTrue(
            $role->hasPermission('contacts.view')
        );
        $this->assertTrue(
            $role->hasPermission('tasks.view')
        );

        $listResponse = $this->actingAs($admin)
            ->getJson(
                '/api/roles?search=Campaign'
                .'&permission_id='.$viewTasks->id
            )
            ->assertOk();

        $listedRoleIds = collect(
            $listResponse->json('data')
        )->pluck('id');

        $this->assertTrue(
            $listedRoleIds->contains($role->id)
        );

        $this->actingAs($admin)
            ->deleteJson('/api/roles/'.$role->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('roles', [
            'id' => $role->id,
        ]);
    }

    public function test_standard_and_assigned_roles_are_protected(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $tenantAdminRole = $this->findRole(
            $admin->tenant,
            'tenant_admin'
        );
        $coordinatorRole = $this->findRole(
            $admin->tenant,
            'coordinator'
        );

        $this->actingAs($admin)
            ->patchJson(
                '/api/roles/'.$coordinatorRole->id,
                [
                    'slug' => 'renamed_coordinator',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        $this->actingAs($admin)
            ->deleteJson(
                '/api/roles/'.$coordinatorRole->id
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $contactsView = $this->findPermission(
            'contacts.view'
        );

        $this->actingAs($admin)
            ->patchJson(
                '/api/roles/'
                .$tenantAdminRole->id
                .'/permissions',
                [
                    'permission_ids' => [
                        $contactsView->id,
                    ],
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'permission_ids'
            );

        $customRole = Role::withoutGlobalScopes()
            ->create([
                'tenant_id' => $admin->tenant_id,
                'name' => 'Assigned Custom Role',
                'slug' => 'assigned_custom_role',
                'description' => 'A role currently assigned.',
            ]);

        $customRole->permissions()->sync([
            $contactsView->id,
        ]);

        $assignedUser = User::factory()->create([
            'tenant_id' => $admin->tenant_id,
        ]);
        $assignedUser->assignRole($customRole);

        $this->actingAs($admin)
            ->deleteJson('/api/roles/'.$customRole->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->assertDatabaseHas('roles', [
            'id' => $customRole->id,
        ]);
    }

    public function test_validation_and_internal_fields_are_protected(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $otherTenant = $this->findTenant(
            'lebanon-future'
        );
        $coordinatorRole = $this->findRole(
            $admin->tenant,
            'coordinator'
        );

        $this->actingAs($admin)
            ->postJson('/api/users', [
                'name' => '',
                'email' => 'invalid-email',
                'password' => 'short',
                'password_confirmation' => 'different',
                'role_ids' => [],
                'tenant_id' => $otherTenant->id,
                'roles' => [
                    'tenant_admin',
                ],
                'permissions' => [
                    'users.manage',
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'email',
                'password',
                'role_ids',
                'tenant_id',
                'roles',
                'permissions',
            ]);

        $targetUser = $this->createUserWithRole(
            $admin->tenant,
            'coordinator'
        );

        $this->actingAs($admin)
            ->patchJson('/api/users/'.$targetUser->id, [
                'tenant_id' => $otherTenant->id,
                'role_ids' => [
                    $coordinatorRole->id,
                ],
                'email_verified_at' => now()->toISOString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'tenant_id',
                'role_ids',
                'email_verified_at',
            ]);

        $this->actingAs($admin)
            ->postJson('/api/roles', [
                'name' => '',
                'slug' => 'INVALID SLUG',
                'permission_ids' => [],
                'tenant_id' => $otherTenant->id,
                'permissions' => [
                    'users.manage',
                ],
                'users' => [
                    $targetUser->id,
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'slug',
                'permission_ids',
                'tenant_id',
                'permissions',
                'users',
            ]);

        $otherTenantRole = $this->findRole(
            $otherTenant,
            'coordinator'
        );

        $this->actingAs($admin)
            ->getJson('/api/roles/'.$otherTenantRole->id)
            ->assertNotFound();
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

    private function findRole(
        Tenant $tenant,
        string $slug
    ): Role {
        return Role::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->firstOrFail();
    }

    private function findPermission(
        string $slug
    ): Permission {
        return Permission::query()
            ->where('slug', $slug)
            ->firstOrFail();
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
