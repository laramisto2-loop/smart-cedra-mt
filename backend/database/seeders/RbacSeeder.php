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
            'contacts.view' => [
                'name' => 'View contacts',
                'description' => 'View tenant CRM contacts and their consent status.',
            ],
            'contacts.create' => [
                'name' => 'Create contacts',
                'description' => 'Create contacts inside the active tenant.',
            ],
            'contacts.update' => [
                'name' => 'Update contacts',
                'description' => 'Update tenant contacts and their operational details.',
            ],
            'contacts.delete' => [
                'name' => 'Delete contacts',
                'description' => 'Delete contacts belonging to the active tenant.',
            ],
            'contacts.consent.manage' => [
                'name' => 'Manage contact consent',
                'description' => 'Record consent and opt-out status for contact channels.',
            ],
            'contacts.import' => [
                'name' => 'Import contacts',
                'description' => 'Bulk import tenant contacts from validated files.',
            ],
            'contacts.export' => [
                'name' => 'Export contacts',
                'description' => 'Export tenant contact data.',
            ],
            'interactions.view' => [
                'name' => 'View contact interactions',
                'description' => 'View tenant contact communication timelines.',
            ],
            'interactions.create' => [
                'name' => 'Create contact interactions',
                'description' => 'Record interactions with tenant contacts.',
            ],
            'interactions.update' => [
                'name' => 'Update contact interactions',
                'description' => 'Correct tenant contact interaction details.',
            ],
            'interactions.delete' => [
                'name' => 'Delete contact interactions',
                'description' => 'Delete tenant contact interaction records.',
            ],
            'segments.view' => [
                'name' => 'View segments',
                'description' => 'View tenant contact segments and their memberships.',
            ],
            'segments.create' => [
                'name' => 'Create segments',
                'description' => 'Create contact segments inside the active tenant.',
            ],
            'segments.update' => [
                'name' => 'Update segments',
                'description' => 'Update tenant contact segment details and criteria.',
            ],
            'segments.delete' => [
                'name' => 'Delete segments',
                'description' => 'Delete contact segments belonging to the active tenant.',
            ],
            'segments.members.manage' => [
                'name' => 'Manage segment members',
                'description' => 'Add and remove contacts from static tenant segments.',
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
                    'contacts.view',
                    'contacts.create',
                    'contacts.update',
                    'contacts.consent.manage',
                    'interactions.view',
                    'interactions.create',
                    'interactions.update',
                    'segments.view',
                    'segments.create',
                    'segments.update',
                    'segments.members.manage',

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
