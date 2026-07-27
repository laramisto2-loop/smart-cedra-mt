<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $permissionDefinitions = [
            'roles.manage' => [
                'name' => 'Manage roles',
                'description' => 'Create roles and manage role permissions.',
            ],
            'users.manage' => [
                'name' => 'Manage users',
                'description' => 'Manage tenant users and their assigned roles.',
            ],
            'geography.view' => [
                'name' => 'View geography',
                'description' => 'View governorates, districts, areas, polling centres, and polling stations.',
            ],
            'geography.create' => [
                'name' => 'Create geography',
                'description' => 'Create tenant geography records.',
            ],
            'geography.update' => [
                'name' => 'Update geography',
                'description' => 'Update tenant geography records.',
            ],
            'geography.delete' => [
                'name' => 'Delete geography',
                'description' => 'Delete tenant geography records.',
            ],
            'audit.view' => [
                'name' => 'View audit logs',
                'description' => 'View tenant audit-log records.',
            ],
        ];

        $permissions = collect($permissionDefinitions)
            ->mapWithKeys(function (array $definition, string $slug): array {
                $permission = Permission::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $definition['name'],
                        'description' => $definition['description'],
                    ]
                );

                return [$slug => $permission];
            });

        $roleDefinitions = [
            'tenant_admin' => [
                'name' => 'Tenant Admin',
                'description' => 'Full administration access inside one tenant.',
                'permissions' => array_keys($permissionDefinitions),
            ],
            'coordinator' => [
                'name' => 'Coordinator',
                'description' => 'Coordinates campaign geography and operational activities.',
                'permissions' => [
                    'geography.view',
                    'geography.create',
                    'geography.update',
                ],
            ],
            'field_agent' => [
                'name' => 'Field Agent',
                'description' => 'Views assigned operational and geography information.',
                'permissions' => [
                    'geography.view',
                ],
            ],
        ];

        $adminEmailsByTenantSlug = [
            'cedra-campaign' => 'admin@cedra.test',
            'lebanon-future' => 'admin@future.test',
        ];

        Tenant::query()->each(function (Tenant $tenant) use (
            $roleDefinitions,
            $permissions,
            $adminEmailsByTenantSlug
        ): void {
            $createdRoles = [];

            foreach ($roleDefinitions as $slug => $definition) {
                $role = Role::withoutGlobalScopes()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'slug' => $slug,
                    ],
                    [
                        'name' => $definition['name'],
                        'description' => $definition['description'],
                    ]
                );

                $permissionIds = collect($definition['permissions'])
                    ->map(fn (string $permissionSlug): int => $permissions[$permissionSlug]->id)
                    ->all();

                $role->permissions()->sync($permissionIds);

                $createdRoles[$slug] = $role;
            }

            $adminEmail = $adminEmailsByTenantSlug[$tenant->slug] ?? null;

            if ($adminEmail !== null) {
                $adminUser = $tenant->users()
                    ->where('email', $adminEmail)
                    ->first();

                if ($adminUser !== null) {
                    $adminUser->assignRole($createdRoles['tenant_admin']);
                }
            }
        });
    }
}
