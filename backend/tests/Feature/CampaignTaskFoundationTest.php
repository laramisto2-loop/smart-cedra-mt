<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\CampaignTask;
use App\Models\Contact;
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

class CampaignTaskFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            TenantSeeder::class,
            GeographySeeder::class,
            RbacSeeder::class,
        ]);
    }

    public function test_task_relationships_and_workflow_timestamps_work(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $tenant = $admin->tenant;
        $area = $this->findArea($admin);
        $contact = $this->createContact(
            $admin,
            'TASK-CONTACT',
            $area
        );

        $fieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $task = $this->createTask(
            $admin,
            $fieldAgent,
            $contact,
            $area
        );

        $this->assertSame($tenant->id, $task->tenant_id);
        $this->assertTrue($task->tenant->is($tenant));
        $this->assertTrue($task->contact->is($contact));
        $this->assertTrue($task->area->is($area));
        $this->assertTrue($task->creator->is($admin));
        $this->assertTrue($task->assignee->is($fieldAgent));

        $this->assertTrue(
            $tenant->campaignTasks()
                ->firstOrFail()
                ->is($task)
        );

        $this->assertTrue(
            $contact->campaignTasks()
                ->firstOrFail()
                ->is($task)
        );

        $this->assertTrue(
            $area->campaignTasks()
                ->firstOrFail()
                ->is($task)
        );

        $this->assertTrue(
            $admin->createdCampaignTasks()
                ->firstOrFail()
                ->is($task)
        );

        $this->assertTrue(
            $fieldAgent->assignedCampaignTasks()
                ->firstOrFail()
                ->is($task)
        );

        $this->assertNull($task->started_at);
        $this->assertNull($task->completed_at);

        $task->update(['status' => 'in_progress']);
        $task->refresh();

        $this->assertNotNull($task->started_at);
        $this->assertNull($task->completed_at);

        $task->update([
            'status' => 'completed',
            'completion_notes' => 'Task completed safely.',
        ]);
        $task->refresh();

        $this->assertNotNull($task->completed_at);

        $task->update(['status' => 'cancelled']);
        $task->refresh();

        $this->assertNull($task->completed_at);
    }

    public function test_tenant_only_queries_its_own_tasks(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $cedraTask = $this->createTask($cedraAdmin);
        $futureTask = $this->createTask($futureAdmin);

        $this->actingAs($cedraAdmin);

        $this->assertCount(1, CampaignTask::all());

        $this->assertTrue(
            CampaignTask::firstOrFail()->is($cedraTask)
        );

        $this->assertNull(
            CampaignTask::find($futureTask->id)
        );

        $this->assertSame(
            2,
            CampaignTask::withoutGlobalScopes()->count()
        );
    }

    public function test_active_tenant_overrides_submitted_tenant_id(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $this->actingAs($cedraAdmin);

        $task = CampaignTask::create([
            'tenant_id' => $futureAdmin->tenant_id,
            'created_by_user_id' => $cedraAdmin->id,
            'title' => 'Tenant override task',
            'type' => 'general',
            'priority' => 'normal',
            'status' => 'pending',
        ]);

        $this->assertSame(
            $cedraAdmin->tenant_id,
            $task->tenant_id
        );
    }

    public function test_task_rejects_cross_tenant_contact_and_area(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $futureArea = $this->findArea($futureAdmin);
        $futureContact = $this->createContact(
            $futureAdmin,
            'FUTURE-TASK-CONTACT',
            $futureArea
        );

        $this->actingAs($cedraAdmin);

        try {
            CampaignTask::create([
                'contact_id' => $futureContact->id,
                'created_by_user_id' => $cedraAdmin->id,
                'title' => 'Invalid contact task',
            ]);

            $this->fail(
                'A cross-tenant contact should have been rejected.'
            );
        } catch (LogicException $exception) {
            $this->assertSame(
                'The task contact must belong to the same tenant.',
                $exception->getMessage()
            );
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'The task area must belong to the same tenant.'
        );

        CampaignTask::create([
            'area_id' => $futureArea->id,
            'created_by_user_id' => $cedraAdmin->id,
            'title' => 'Invalid area task',
        ]);
    }

    public function test_task_rejects_cross_tenant_creator(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $this->actingAs($cedraAdmin);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'The task creator must belong to the same tenant.'
        );

        CampaignTask::create([
            'created_by_user_id' => $futureAdmin->id,
            'title' => 'Invalid creator task',
        ]);
    }

    public function test_task_rejects_cross_tenant_assignee(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $this->actingAs($cedraAdmin);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'The task assignee must belong to the same tenant.'
        );

        CampaignTask::create([
            'created_by_user_id' => $cedraAdmin->id,
            'assigned_to_user_id' => $futureAdmin->id,
            'title' => 'Invalid assignee task',
        ]);
    }

    public function test_task_policy_enforces_roles_assignments_and_tenants(): void
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

        $otherFieldAgent = $this->createUserWithRole(
            $cedraTenant,
            'field_agent'
        );

        $assignedTask = $this->createTask(
            $admin,
            $fieldAgent
        );

        $otherAssignedTask = $this->createTask(
            $admin,
            $otherFieldAgent
        );

        $otherTenantTask = $this->createTask(
            $futureAdmin,
            $futureAdmin
        );

        $this->actingAs($admin);

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'viewAny',
                CampaignTask::class
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'view',
                $assignedTask
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'create',
                CampaignTask::class
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'update',
                $assignedTask
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'assign',
                $assignedTask
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'complete',
                $assignedTask
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'delete',
                $assignedTask
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'viewAny',
                CampaignTask::class
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'view',
                $assignedTask
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'create',
                CampaignTask::class
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'update',
                $assignedTask
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'assign',
                $assignedTask
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'complete',
                $assignedTask
            )
        );

        $this->assertFalse(
            Gate::forUser($coordinator)->allows(
                'delete',
                $assignedTask
            )
        );

        $this->assertTrue(
            Gate::forUser($fieldAgent)->allows(
                'viewAny',
                CampaignTask::class
            )
        );

        $this->assertTrue(
            Gate::forUser($fieldAgent)->allows(
                'view',
                $assignedTask
            )
        );

        $this->assertTrue(
            Gate::forUser($fieldAgent)->allows(
                'complete',
                $assignedTask
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'view',
                $otherAssignedTask
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'complete',
                $otherAssignedTask
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'create',
                CampaignTask::class
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'update',
                $assignedTask
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'assign',
                $assignedTask
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'delete',
                $assignedTask
            )
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'view',
                $otherTenantTask
            )
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'update',
                $otherTenantTask
            )
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'assign',
                $otherTenantTask
            )
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'complete',
                $otherTenantTask
            )
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'delete',
                $otherTenantTask
            )
        );
    }

    private function createTask(
        User $creator,
        ?User $assignee = null,
        ?Contact $contact = null,
        ?Area $area = null
    ): CampaignTask {
        $this->actingAs($creator);

        return CampaignTask::create([
            'contact_id' => $contact?->id,
            'area_id' => $area?->id,
            'created_by_user_id' => $creator->id,
            'assigned_to_user_id' => $assignee?->id,
            'title' => 'Foundation test task',
            'description' => 'A fictional campaign task.',
            'type' => 'follow_up',
            'priority' => 'high',
            'status' => 'pending',
            'due_at' => now()->addDay(),
        ]);
    }

    private function createContact(
        User $user,
        string $referenceCode,
        ?Area $area = null
    ): Contact {
        $this->actingAs($user);

        return Contact::create([
            'area_id' => $area?->id,
            'created_by_user_id' => $user->id,
            'reference_code' => $referenceCode,
            'first_name' => 'Task',
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

    private function findArea(User $user): Area
    {
        return Area::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
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
