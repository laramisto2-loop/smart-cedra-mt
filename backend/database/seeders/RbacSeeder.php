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
            'tasks.view' => [
                'name' => 'View campaign tasks',
                'description' => 'View campaign tasks belonging to the active tenant.',
            ],
            'tasks.create' => [
                'name' => 'Create campaign tasks',
                'description' => 'Create campaign tasks inside the active tenant.',
            ],
            'tasks.update' => [
                'name' => 'Update campaign tasks',
                'description' => 'Update campaign task details and workflow status.',
            ],
            'tasks.assign' => [
                'name' => 'Assign campaign tasks',
                'description' => 'Assign campaign tasks to users in the active tenant.',
            ],
            'tasks.complete' => [
                'name' => 'Complete campaign tasks',
                'description' => 'Complete assigned campaign tasks and record completion notes.',
            ],
            'tasks.delete' => [
                'name' => 'Delete campaign tasks',
                'description' => 'Delete campaign tasks belonging to the active tenant.',
            ],
            'incidents.view' => [
                'name' => 'View incidents',
                'description' => 'View incident reports accessible to the active tenant user.',
            ],
            'incidents.create' => [
                'name' => 'Create incidents',
                'description' => 'Submit incident reports inside the active tenant.',
            ],
            'incidents.update' => [
                'name' => 'Update incidents',
                'description' => 'Update accessible incident details before or during review.',
            ],
            'incidents.assign' => [
                'name' => 'Assign incidents',
                'description' => 'Assign tenant incidents to operational reviewers.',
            ],
            'incidents.review' => [
                'name' => 'Review incidents',
                'description' => 'Review, resolve, or dismiss tenant incident reports.',
            ],
            'incidents.attachments.manage' => [
                'name' => 'Manage incident attachments',
                'description' => 'Upload and remove attachments belonging to accessible incidents.',
            ],
            'incidents.delete' => [
                'name' => 'Delete incidents',
                'description' => 'Delete incident reports belonging to the active tenant.',
            ],
            'turnout.view' => [
                'name' => 'View turnout snapshots',
                'description' => 'View aggregate turnout snapshots belonging to the active tenant.',
            ],
            'turnout.create' => [
                'name' => 'Create turnout snapshots',
                'description' => 'Submit aggregate polling-centre and polling-station turnout counts.',
            ],
            'messages.templates.view' => [
                'name' => 'View message templates',
                'description' => 'View SMS and WhatsApp templates belonging to the active tenant.',
            ],
            'messages.templates.create' => [
                'name' => 'Create message templates',
                'description' => 'Create SMS and WhatsApp templates inside the active tenant.',
            ],
            'messages.templates.update' => [
                'name' => 'Update message templates',
                'description' => 'Update message templates belonging to the active tenant.',
            ],
            'messages.templates.approve' => [
                'name' => 'Approve message templates',
                'description' => 'Approve tenant message templates for outbound use.',
            ],
            'messages.templates.delete' => [
                'name' => 'Delete message templates',
                'description' => 'Delete unused message templates belonging to the active tenant.',
            ],
            'messages.view' => [
                'name' => 'View outbound messages',
                'description' => 'View tenant outbound message records and delivery status.',
            ],
            'messages.send' => [
                'name' => 'Send outbound messages',
                'description' => 'Queue consent-aware SMS and WhatsApp messages.',
            ],
            'messages.delivery.view' => [
                'name' => 'View message delivery events',
                'description' => 'View immutable provider delivery events for tenant messages.',
            ],
            'calls.scripts.view' => [
                'name' => 'View call scripts',
                'description' => 'View call-center scripts belonging to the active tenant.',
            ],
            'calls.scripts.create' => [
                'name' => 'Create call scripts',
                'description' => 'Create call-center scripts inside the active tenant.',
            ],
            'calls.scripts.update' => [
                'name' => 'Update call scripts',
                'description' => 'Update draft and archived call scripts belonging to the active tenant.',
            ],
            'calls.scripts.activate' => [
                'name' => 'Activate call scripts',
                'description' => 'Activate or archive tenant call-center scripts.',
            ],
            'calls.scripts.delete' => [
                'name' => 'Delete call scripts',
                'description' => 'Delete unused call-center scripts belonging to the active tenant.',
            ],
            'calls.queues.view' => [
                'name' => 'View call queues',
                'description' => 'View call queues belonging to the active tenant.',
            ],
            'calls.queues.create' => [
                'name' => 'Create call queues',
                'description' => 'Create organized calling campaigns inside the active tenant.',
            ],
            'calls.queues.update' => [
                'name' => 'Update call queues',
                'description' => 'Update tenant call queues and their workflow status.',
            ],
            'calls.queues.assign' => [
                'name' => 'Assign call queues',
                'description' => 'Assign tenant contacts and agents to call queues.',
            ],
            'calls.queues.delete' => [
                'name' => 'Delete call queues',
                'description' => 'Delete unused call queues belonging to the active tenant.',
            ],
            'calls.assignments.view' => [
                'name' => 'View call assignments',
                'description' => 'View call assignments accessible to the active tenant user.',
            ],
            'calls.assignments.claim' => [
                'name' => 'Claim call assignments',
                'description' => 'Claim accessible pending call assignments for call-center work.',
            ],
            'calls.assignments.update' => [
                'name' => 'Update call assignments',
                'description' => 'Update accessible call assignments and their workflow status.',
            ],
            'calls.attempts.view' => [
                'name' => 'View call attempts',
                'description' => 'View immutable call-attempt history accessible to the active tenant user.',
            ],
            'calls.attempts.create' => [
                'name' => 'Record call attempts',
                'description' => 'Record call outcomes, notes, durations, and callback requirements.',
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
                    'tasks.view',
                    'tasks.create',
                    'tasks.update',
                    'tasks.assign',
                    'tasks.complete',
                    'incidents.view',
                    'incidents.create',
                    'incidents.update',
                    'incidents.assign',
                    'incidents.review',
                    'incidents.attachments.manage',
                    'turnout.view',
                    'turnout.create',
                    'messages.templates.view',
                    'messages.templates.create',
                    'messages.templates.update',
                    'messages.view',
                    'messages.send',
                    'messages.delivery.view',
                    'calls.scripts.view',
                    'calls.scripts.create',
                    'calls.scripts.update',
                    'calls.queues.view',
                    'calls.queues.create',
                    'calls.queues.update',
                    'calls.queues.assign',
                    'calls.assignments.view',
                    'calls.assignments.claim',
                    'calls.assignments.update',
                    'calls.attempts.view',
                    'calls.attempts.create',
                ],
            ],
            'field_agent' => [
                'name' => 'Field Agent',
                'description' => 'Views assigned operational and geography information.',
                'permissions' => [
                    'geography.view',
                    'tasks.view',
                    'tasks.complete',
                    'incidents.view',
                    'incidents.create',
                    'incidents.update',
                    'incidents.attachments.manage',
                    'turnout.view',
                    'turnout.create',
                    'calls.assignments.view',
                    'calls.assignments.claim',
                    'calls.assignments.update',
                    'calls.attempts.view',
                    'calls.attempts.create',
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
