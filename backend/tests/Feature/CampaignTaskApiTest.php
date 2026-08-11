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
use Tests\TestCase;

class CampaignTaskApiTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_task_api(): void
    {
        $this->getJson('/api/campaign-tasks')
            ->assertUnauthorized();

        $this->postJson('/api/campaign-tasks', [])
            ->assertUnauthorized();

        $this->getJson('/api/campaign-tasks/1')
            ->assertUnauthorized();

        $this->getJson('/api/campaign-tasks/assignees')
            ->assertUnauthorized();

        $this->patchJson(
            '/api/campaign-tasks/1/assign',
            ['assigned_to_user_id' => null]
        )
            ->assertUnauthorized();

        $this->patchJson(
            '/api/campaign-tasks/1/complete',
            ['completion_notes' => 'Done']
        )
            ->assertUnauthorized();
    }

    public function test_admin_only_receives_own_tenant_assignees(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');
        $admin = $this->cedraAdmin();

        $cedraFieldAgent = $this->createUserWithRole(
            $cedraTenant,
            'field_agent'
        );

        $futureFieldAgent = $this->createUserWithRole(
            $futureTenant,
            'field_agent'
        );

        $response = $this->actingAs($admin)
            ->getJson('/api/campaign-tasks/assignees')
            ->assertOk();

        $assigneeIds = collect($response->json('data'))
            ->pluck('id');

        $this->assertTrue(
            $assigneeIds->contains($admin->id)
        );

        $this->assertTrue(
            $assigneeIds->contains($cedraFieldAgent->id)
        );

        $this->assertFalse(
            $assigneeIds->contains($futureFieldAgent->id)
        );

        $this->actingAs($cedraFieldAgent)
            ->getJson('/api/campaign-tasks/assignees')
            ->assertForbidden();
    }

    public function test_admin_only_receives_and_filters_own_tasks(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');
        $admin = $this->cedraAdmin();
        $futureAdmin = $this->futureAdmin();

        $fieldAgent = $this->createUserWithRole(
            $cedraTenant,
            'field_agent'
        );

        $this->createTask(
            $cedraTenant,
            $admin,
            [
                'assigned_to_user_id' => $fieldAgent->id,
                'title' => 'Urgent volunteer follow-up',
                'type' => 'follow_up',
                'priority' => 'urgent',
                'status' => 'pending',
                'due_at' => now()->subDay(),
            ]
        );

        $this->createTask(
            $cedraTenant,
            $admin,
            [
                'title' => 'Future data entry',
                'type' => 'data_entry',
                'priority' => 'normal',
                'status' => 'in_progress',
                'due_at' => now()->addDays(2),
            ]
        );

        $this->createTask(
            $futureTenant,
            $futureAdmin,
            [
                'title' => 'Other tenant task',
            ]
        );

        $this->actingAs($admin)
            ->getJson('/api/campaign-tasks')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing([
                'title' => 'Other tenant task',
            ]);

        $this->getJson(
            '/api/campaign-tasks?search=volunteer'
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.title',
                'Urgent volunteer follow-up'
            );

        $this->getJson(
            '/api/campaign-tasks'
            .'?type=follow_up'
            .'&priority=urgent'
            .'&status=pending'
            .'&overdue=1'
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_overdue', true);

        $this->getJson(
            '/api/campaign-tasks'
            ."?assigned_to_user_id={$fieldAgent->id}"
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.assignee.id',
                $fieldAgent->id
            );
    }

    public function test_tenant_admin_can_create_update_assign_complete_view_and_delete_task(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $admin = $this->cedraAdmin();
        $area = $this->findArea($tenant);

        $contact = $this->createContact(
            $tenant,
            'TASK-API-CONTACT',
            $area
        );

        $firstAssignee = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $secondAssignee = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $response = $this->actingAs($admin)
            ->postJson('/api/campaign-tasks', [
                'contact_id' => $contact->id,
                'area_id' => $area->id,
                'assigned_to_user_id' => $firstAssignee->id,
                'title' => 'Call volunteer',
                'description' => 'Confirm availability.',
                'type' => 'phone_call',
                'priority' => 'high',
                'due_at' => now()->addDay()->toISOString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Call volunteer')
            ->assertJsonPath('data.type', 'phone_call')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath(
                'data.contact.reference_code',
                'TASK-API-CONTACT'
            )
            ->assertJsonPath(
                'data.assignee.id',
                $firstAssignee->id
            )
            ->assertJsonPath('data.creator.id', $admin->id);

        $taskId = $response->json('data.id');

        $this->assertDatabaseHas('campaign_tasks', [
            'id' => $taskId,
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $admin->id,
            'assigned_to_user_id' => $firstAssignee->id,
        ]);

        $this->patchJson(
            "/api/campaign-tasks/{$taskId}",
            [
                'title' => 'Call priority volunteer',
                'status' => 'in_progress',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.title',
                'Call priority volunteer'
            )
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath(
                'data.started_at',
                fn ($value) => $value !== null
            );

        $this->patchJson(
            "/api/campaign-tasks/{$taskId}/assign",
            [
                'assigned_to_user_id' => $secondAssignee->id,
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.assignee.id',
                $secondAssignee->id
            );

        $this->patchJson(
            "/api/campaign-tasks/{$taskId}/complete",
            [
                'completion_notes' => 'Volunteer confirmed.',
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath(
                'data.completion_notes',
                'Volunteer confirmed.'
            )
            ->assertJsonPath(
                'data.completed_at',
                fn ($value) => $value !== null
            );

        $this->getJson("/api/campaign-tasks/{$taskId}")
            ->assertOk()
            ->assertJsonPath('data.id', $taskId);

        $this->deleteJson("/api/campaign-tasks/{$taskId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('campaign_tasks', [
            'id' => $taskId,
        ]);
    }

    public function test_task_validation_protects_tenant_relationships_and_workflow_fields(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');
        $admin = $this->cedraAdmin();
        $futureAdmin = $this->futureAdmin();

        $futureArea = $this->findArea($futureTenant);

        $futureContact = $this->createContact(
            $futureTenant,
            'FUTURE-TASK-API',
            $futureArea
        );

        $this->actingAs($admin)
            ->postJson('/api/campaign-tasks', [
                'tenant_id' => $futureTenant->id,
                'created_by_user_id' => $futureAdmin->id,
                'contact_id' => $futureContact->id,
                'area_id' => $futureArea->id,
                'assigned_to_user_id' => $futureAdmin->id,
                'title' => '',
                'type' => 'invalid',
                'priority' => 'emergency',
                'status' => 'completed',
                'started_at' => now()->toISOString(),
                'completed_at' => now()->toISOString(),
                'completion_notes' => 'Forged completion.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'tenant_id',
                'created_by_user_id',
                'contact_id',
                'area_id',
                'assigned_to_user_id',
                'title',
                'type',
                'priority',
                'status',
                'started_at',
                'completed_at',
                'completion_notes',
            ]);

        $task = $this->createTask(
            $cedraTenant,
            $admin
        );

        $this->patchJson(
            "/api/campaign-tasks/{$task->id}",
            [
                'assigned_to_user_id' => $futureAdmin->id,
                'status' => 'completed',
                'completed_at' => now()->toISOString(),
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'assigned_to_user_id',
                'status',
                'completed_at',
            ]);

        $this->patchJson(
            "/api/campaign-tasks/{$task->id}/assign",
            [
                'assigned_to_user_id' => $futureAdmin->id,
                'tenant_id' => $futureTenant->id,
                'created_by_user_id' => $futureAdmin->id,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'assigned_to_user_id',
                'tenant_id',
                'created_by_user_id',
            ]);

        $this->patchJson(
            "/api/campaign-tasks/{$task->id}/complete",
            [
                'status' => 'completed',
                'completed_at' => now()->toISOString(),
                'assigned_to_user_id' => $futureAdmin->id,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
                'completed_at',
                'assigned_to_user_id',
            ]);
    }

    public function test_coordinator_can_manage_tasks_but_cannot_delete(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $coordinator = $this->createUserWithRole(
            $tenant,
            'coordinator'
        );

        $fieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $response = $this->actingAs($coordinator)
            ->postJson('/api/campaign-tasks', [
                'assigned_to_user_id' => $fieldAgent->id,
                'title' => 'Coordinator task',
                'type' => 'field_visit',
                'priority' => 'normal',
            ])
            ->assertCreated();

        $taskId = $response->json('data.id');

        $this->patchJson(
            "/api/campaign-tasks/{$taskId}",
            [
                'priority' => 'high',
                'status' => 'in_progress',
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.priority', 'high');

        $this->patchJson(
            "/api/campaign-tasks/{$taskId}/assign",
            ['assigned_to_user_id' => null]
        )
            ->assertOk()
            ->assertJsonPath('data.assignee', null);

        $this->patchJson(
            "/api/campaign-tasks/{$taskId}/assign",
            ['assigned_to_user_id' => $fieldAgent->id]
        )
            ->assertOk();

        $this->patchJson(
            "/api/campaign-tasks/{$taskId}/complete",
            ['completion_notes' => 'Reviewed by coordinator.']
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->deleteJson("/api/campaign-tasks/{$taskId}")
            ->assertForbidden();

        $this->assertDatabaseHas('campaign_tasks', [
            'id' => $taskId,
        ]);
    }

    public function test_field_agent_only_views_and_completes_assigned_tasks(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $admin = $this->cedraAdmin();

        $fieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $otherFieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $assignedTask = $this->createTask(
            $tenant,
            $admin,
            [
                'assigned_to_user_id' => $fieldAgent->id,
                'title' => 'Assigned field task',
            ]
        );

        $otherTask = $this->createTask(
            $tenant,
            $admin,
            [
                'assigned_to_user_id' => $otherFieldAgent->id,
                'title' => 'Other field task',
            ]
        );

        $this->actingAs($fieldAgent)
            ->getJson('/api/campaign-tasks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $assignedTask->id
            )
            ->assertJsonMissing([
                'title' => 'Other field task',
            ]);

        $this->getJson(
            "/api/campaign-tasks/{$assignedTask->id}"
        )
            ->assertOk();

        $this->getJson(
            "/api/campaign-tasks/{$otherTask->id}"
        )
            ->assertForbidden();

        $this->patchJson(
            "/api/campaign-tasks/{$assignedTask->id}/complete",
            ['completion_notes' => 'Completed in the field.']
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->postJson('/api/campaign-tasks', [
            'title' => 'Forbidden task',
            'type' => 'general',
            'priority' => 'normal',
        ])
            ->assertForbidden();

        $this->patchJson(
            "/api/campaign-tasks/{$assignedTask->id}",
            ['title' => 'Forbidden update']
        )
            ->assertForbidden();

        $this->patchJson(
            "/api/campaign-tasks/{$assignedTask->id}/assign",
            ['assigned_to_user_id' => $fieldAgent->id]
        )
            ->assertForbidden();

        $this->deleteJson(
            "/api/campaign-tasks/{$assignedTask->id}"
        )
            ->assertForbidden();
    }

    public function test_cancelled_task_cannot_be_completed(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $admin = $this->cedraAdmin();

        $task = $this->createTask(
            $tenant,
            $admin,
            ['status' => 'cancelled']
        );

        $this->actingAs($admin)
            ->patchJson(
                "/api/campaign-tasks/{$task->id}/complete",
                ['completion_notes' => 'Should not save.']
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('campaign_tasks', [
            'id' => $task->id,
            'status' => 'cancelled',
            'completed_at' => null,
        ]);
    }

    public function test_admin_cannot_access_another_tenants_task(): void
    {
        $futureTenant = $this->findTenant('lebanon-future');
        $futureAdmin = $this->futureAdmin();

        $task = $this->createTask(
            $futureTenant,
            $futureAdmin
        );

        $this->actingAs($this->cedraAdmin())
            ->getJson("/api/campaign-tasks/{$task->id}")
            ->assertNotFound();

        $this->patchJson(
            "/api/campaign-tasks/{$task->id}",
            ['title' => 'Forbidden']
        )
            ->assertNotFound();

        $this->patchJson(
            "/api/campaign-tasks/{$task->id}/assign",
            ['assigned_to_user_id' => null]
        )
            ->assertNotFound();

        $this->patchJson(
            "/api/campaign-tasks/{$task->id}/complete",
            ['completion_notes' => 'Forbidden']
        )
            ->assertNotFound();

        $this->deleteJson("/api/campaign-tasks/{$task->id}")
            ->assertNotFound();
    }

    public function test_invalid_task_filters_are_rejected(): void
    {
        $this->actingAs($this->cedraAdmin())
            ->getJson(
                '/api/campaign-tasks'
                .'?type=invalid'
                .'&priority=critical'
                .'&status=deleted'
                .'&mine=perhaps'
                .'&overdue=perhaps'
                .'&due_from=tomorrow'
                .'&per_page=500'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'type',
                'priority',
                'status',
                'mine',
                'overdue',
                'due_from',
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

    private function findArea(Tenant $tenant): Area
    {
        return Area::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
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

    private function createContact(
        Tenant $tenant,
        string $referenceCode,
        ?Area $area = null
    ): Contact {
        return Contact::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'area_id' => $area?->id,
            'reference_code' => $referenceCode,
            'first_name' => 'Task',
            'last_name' => 'Contact',
            'preferred_language' => 'en',
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createTask(
        Tenant $tenant,
        User $creator,
        array $attributes = []
    ): CampaignTask {
        return CampaignTask::withoutGlobalScopes()->create(
            array_merge(
                [
                    'title' => 'Test campaign task',
                    'description' => 'Fictional task for API testing.',
                    'type' => 'general',
                    'priority' => 'normal',
                    'status' => 'pending',
                    'due_at' => now()->addDay(),
                ],
                $attributes,
                [
                    'tenant_id' => $tenant->id,
                    'created_by_user_id' => $creator->id,
                ]
            )
        );
    }
}
