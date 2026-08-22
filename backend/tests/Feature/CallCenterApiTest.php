<?php

namespace Tests\Feature;

use App\Models\CallAssignment;
use App\Models\CallAttempt;
use App\Models\CallQueue;
use App\Models\CallScript;
use App\Models\CampaignTask;
use App\Models\Contact;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class CallCenterApiTest extends TestCase
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_unauthenticated_user_cannot_access_call_center_api(): void
    {
        $this->getJson('/api/call-scripts')
            ->assertUnauthorized();

        $this->postJson('/api/call-scripts', [])
            ->assertUnauthorized();

        $this->getJson('/api/call-queues')
            ->assertUnauthorized();

        $this->postJson('/api/call-queues', [])
            ->assertUnauthorized();

        $this->getJson('/api/call-assignments')
            ->assertUnauthorized();

        $this->getJson('/api/call-attempts')
            ->assertUnauthorized();

        $this->postJson('/api/call-attempts', [])
            ->assertUnauthorized();
    }

    public function test_user_without_call_center_permissions_is_forbidden(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($user)
            ->getJson('/api/call-scripts')
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson('/api/call-scripts', [])
            ->assertForbidden();

        $this->actingAs($user)
            ->getJson('/api/call-queues')
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson('/api/call-queues', [])
            ->assertForbidden();

        $this->actingAs($user)
            ->getJson('/api/call-assignments')
            ->assertForbidden();

        $this->actingAs($user)
            ->getJson('/api/call-attempts')
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson('/api/call-attempts', [])
            ->assertForbidden();
    }

    public function test_admin_manages_scripts_and_only_sees_own_tenant(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $futureScript = $this->createScript(
            $futureAdmin,
            [
                'name' => 'Future tenant script',
                'code' => 'FUTURE_SCRIPT',
            ]
        );

        $response = $this->actingAs($admin)
            ->postJson(
                '/api/call-scripts',
                $this->validScriptPayload([
                    'code' => 'volunteer_confirmation',
                ])
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.code',
                'VOLUNTEER_CONFIRMATION'
            )
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath(
                'data.created_by_user_id',
                $admin->id
            );

        $scriptId = $response->json('data.id');

        $this->actingAs($admin)
            ->patchJson(
                "/api/call-scripts/{$scriptId}",
                [
                    'name' => 'Updated volunteer script',
                    'description' => 'Updated call instructions.',
                    'body' => 'Confirm availability and record the response.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Updated volunteer script'
            )
            ->assertJsonPath(
                'data.status',
                'draft'
            );

        $this->actingAs($admin)
            ->patchJson(
                "/api/call-scripts/{$scriptId}/activate",
                [
                    'status' => 'active',
                ]
            )
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $listResponse = $this->actingAs($admin)
            ->getJson(
                '/api/call-scripts'
                .'?search=Updated'
                .'&status=active'
            )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $scriptId);

        $visibleIds = collect(
            $listResponse->json('data')
        )->pluck('id');

        $this->assertTrue(
            $visibleIds->contains($scriptId)
        );

        $this->assertFalse(
            $visibleIds->contains($futureScript->id)
        );

        $this->actingAs($admin)
            ->getJson(
                "/api/call-scripts/{$futureScript->id}"
            )
            ->assertNotFound();

        $draftScript = $this->actingAs($admin)
            ->postJson(
                '/api/call-scripts',
                $this->validScriptPayload([
                    'name' => 'Disposable draft script',
                    'code' => 'DISPOSABLE_DRAFT',
                ])
            )
            ->assertCreated();

        $draftScriptId = $draftScript->json('data.id');

        $this->actingAs($admin)
            ->deleteJson(
                "/api/call-scripts/{$draftScriptId}"
            )
            ->assertNoContent();

        $this->assertDatabaseMissing('call_scripts', [
            'id' => $draftScriptId,
        ]);
    }

    public function test_coordinator_can_draft_but_cannot_activate_or_delete_scripts(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $coordinator = $this->createUserWithRole(
            $tenant,
            'coordinator'
        );

        $response = $this->actingAs($coordinator)
            ->postJson(
                '/api/call-scripts',
                $this->validScriptPayload([
                    'name' => 'Coordinator draft script',
                    'code' => 'COORDINATOR_DRAFT',
                ])
            )
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath(
                'data.created_by_user_id',
                $coordinator->id
            );

        $scriptId = $response->json('data.id');

        $this->actingAs($coordinator)
            ->patchJson(
                "/api/call-scripts/{$scriptId}",
                [
                    'description' => (
                        'Updated by the coordinator.'
                    ),
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.description',
                'Updated by the coordinator.'
            );

        $this->actingAs($coordinator)
            ->patchJson(
                "/api/call-scripts/{$scriptId}/activate",
                [
                    'status' => 'active',
                ]
            )
            ->assertForbidden();

        $this->actingAs($coordinator)
            ->deleteJson(
                "/api/call-scripts/{$scriptId}"
            )
            ->assertForbidden();

        $this->assertDatabaseHas('call_scripts', [
            'id' => $scriptId,
            'status' => 'draft',
        ]);
    }

    public function test_admin_creates_queue_and_bulk_assigns_contacts(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $admin = $this->findUser('admin@cedra.test');

        $fieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $script = $this->createScript($admin);

        $response = $this->actingAs($admin)
            ->postJson(
                '/api/call-queues',
                $this->validQueuePayload(
                    $script,
                    [
                        'code' => 'volunteer_queue',
                    ]
                )
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.code',
                'VOLUNTEER_QUEUE'
            )
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath(
                'data.call_script_id',
                $script->id
            );

        $queueId = $response->json('data.id');

        $firstContact = $this->createContact(
            $admin,
            [
                'first_name' => 'Maya',
                'last_name' => 'Haddad',
            ]
        );

        $secondContact = $this->createContact(
            $admin,
            [
                'first_name' => 'Karim',
                'last_name' => 'Nasser',
            ]
        );

        $assignResponse = $this->actingAs($admin)
            ->postJson(
                "/api/call-queues/{$queueId}/assign",
                [
                    'contact_ids' => [
                        $firstContact->id,
                        $secondContact->id,
                    ],
                    'assigned_to_user_id' => $fieldAgent->id,
                    'priority' => 'high',
                    'scheduled_for' => now()
                        ->addHour()
                        ->toISOString(),
                    'notes' => (
                        'Call both contacts for confirmation.'
                    ),
                ]
            )
            ->assertCreated()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath(
                'data.0.assigned_to_user_id',
                $fieldAgent->id
            )
            ->assertJsonPath(
                'data.0.status',
                'pending'
            )
            ->assertJsonPath(
                'data.0.priority',
                'high'
            );

        $assignmentIds = collect(
            $assignResponse->json('data')
        )->pluck('id');

        $this->assertCount(2, $assignmentIds);

        $this->assertDatabaseHas('call_assignments', [
            'tenant_id' => $tenant->id,
            'call_queue_id' => $queueId,
            'contact_id' => $firstContact->id,
            'assigned_to_user_id' => $fieldAgent->id,
            'assigned_by_user_id' => $admin->id,
            'status' => 'pending',
            'priority' => 'high',
        ]);

        $this->assertDatabaseHas('call_assignments', [
            'tenant_id' => $tenant->id,
            'call_queue_id' => $queueId,
            'contact_id' => $secondContact->id,
            'assigned_to_user_id' => $fieldAgent->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->getJson("/api/call-queues/{$queueId}")
            ->assertOk()
            ->assertJsonPath(
                'data.assignments_count',
                2
            )
            ->assertJsonCount(
                2,
                'data.assignments'
            );
    }

    public function test_field_agent_only_accesses_owned_assignments_and_can_claim_unassigned_work(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $admin = $this->findUser('admin@cedra.test');

        $fieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $otherFieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $queue = $this->createQueue($admin);

        $ownAssignment = $this->createAssignment(
            $admin,
            [
                'call_queue_id' => $queue->id,
                'assigned_to_user_id' => $fieldAgent->id,
            ]
        );

        $otherAssignment = $this->createAssignment(
            $admin,
            [
                'call_queue_id' => $queue->id,
                'assigned_to_user_id' => (
                    $otherFieldAgent->id
                ),
            ]
        );

        $unassigned = $this->createAssignment(
            $admin,
            [
                'call_queue_id' => $queue->id,
                'assigned_to_user_id' => null,
            ]
        );

        $listResponse = $this->actingAs($fieldAgent)
            ->getJson('/api/call-assignments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $ownAssignment->id
            );

        $visibleIds = collect(
            $listResponse->json('data')
        )->pluck('id');

        $this->assertTrue(
            $visibleIds->contains($ownAssignment->id)
        );

        $this->assertFalse(
            $visibleIds->contains($otherAssignment->id)
        );

        $this->assertFalse(
            $visibleIds->contains($unassigned->id)
        );

        $this->actingAs($fieldAgent)
            ->getJson(
                "/api/call-assignments/{$otherAssignment->id}"
            )
            ->assertForbidden();

        $this->actingAs($fieldAgent)
            ->patchJson(
                "/api/call-assignments/{$unassigned->id}/claim"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.assigned_to_user_id',
                $fieldAgent->id
            )
            ->assertJsonPath(
                'data.status',
                'in_progress'
            );

        $unassigned->refresh();

        $this->assertSame(
            $fieldAgent->id,
            $unassigned->assigned_to_user_id
        );

        $this->assertSame(
            'in_progress',
            $unassigned->status
        );

        $this->assertNotNull(
            $unassigned->claimed_at
        );
    }

    public function test_field_agent_records_idempotent_callback_attempt_and_follow_up_task(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-22 15:00:00')
        );

        $tenant = $this->findTenant('cedra-campaign');
        $admin = $this->findUser('admin@cedra.test');

        $fieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $queue = $this->createQueue($admin);

        $contact = $this->createContact(
            $admin,
            [
                'first_name' => 'Maya',
                'last_name' => 'Haddad',
            ]
        );

        $assignment = $this->createAssignment(
            $admin,
            [
                'call_queue_id' => $queue->id,
                'contact_id' => $contact->id,
                'assigned_to_user_id' => $fieldAgent->id,
            ]
        );

        $clientUuid = Str::uuid()->toString();

        $payload = [
            'call_assignment_id' => $assignment->id,
            'client_uuid' => $clientUuid,
            'outcome' => 'callback_requested',
            'duration_seconds' => 75,
            'notes' => (
                'Contact requested a callback tomorrow.'
            ),
            'attempted_at' => now()->toISOString(),
            'follow_up_at' => now()
                ->addDay()
                ->toISOString(),
        ];

        $response = $this->actingAs($fieldAgent)
            ->postJson('/api/call-attempts', $payload)
            ->assertCreated()
            ->assertJsonPath(
                'data.call_assignment_id',
                $assignment->id
            )
            ->assertJsonPath(
                'data.performed_by_user_id',
                $fieldAgent->id
            )
            ->assertJsonPath(
                'data.client_uuid',
                $clientUuid
            )
            ->assertJsonPath(
                'data.outcome',
                'callback_requested'
            );

        $attemptId = $response->json('data.id');
        $followUpTaskId = $response->json(
            'data.follow_up_task_id'
        );

        $this->assertNotNull($followUpTaskId);

        $this->actingAs($fieldAgent)
            ->postJson('/api/call-attempts', $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $attemptId)
            ->assertJsonPath(
                'data.follow_up_task_id',
                $followUpTaskId
            );

        $this->assertSame(
            1,
            CallAttempt::withoutGlobalScopes()
                ->where('client_uuid', $clientUuid)
                ->count()
        );

        $this->assertSame(
            1,
            CampaignTask::withoutGlobalScopes()
                ->whereKey($followUpTaskId)
                ->count()
        );

        $this->assertDatabaseHas('campaign_tasks', [
            'id' => $followUpTaskId,
            'tenant_id' => $tenant->id,
            'contact_id' => $contact->id,
            'created_by_user_id' => $fieldAgent->id,
            'assigned_to_user_id' => $fieldAgent->id,
            'type' => 'follow_up',
            'priority' => $assignment->priority,
            'status' => 'pending',
        ]);

        $assignment->refresh();

        $this->assertSame(
            'in_progress',
            $assignment->status
        );

        $this->assertNotNull(
            $assignment->last_attempted_at
        );
    }

    public function test_completed_call_attempt_completes_assignment(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-22 16:00:00')
        );

        $tenant = $this->findTenant('cedra-campaign');
        $admin = $this->findUser('admin@cedra.test');

        $fieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $assignment = $this->createAssignment(
            $admin,
            [
                'assigned_to_user_id' => $fieldAgent->id,
            ]
        );

        $this->actingAs($fieldAgent)
            ->postJson(
                '/api/call-attempts',
                [
                    'call_assignment_id' => (
                        $assignment->id
                    ),
                    'client_uuid' => (
                        Str::uuid()->toString()
                    ),
                    'outcome' => 'completed',
                    'duration_seconds' => 120,
                    'notes' => (
                        'Contact confirmed participation.'
                    ),
                    'attempted_at' => now()->toISOString(),
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.outcome',
                'completed'
            )
            ->assertJsonPath(
                'data.performed_by_user_id',
                $fieldAgent->id
            );

        $assignment->refresh();

        $this->assertSame(
            'completed',
            $assignment->status
        );

        $this->assertNotNull(
            $assignment->completed_at
        );

        $this->assertNotNull(
            $assignment->last_attempted_at
        );
    }

    public function test_validation_and_tenant_isolation_protect_call_center_data(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $futureScript = $this->createScript(
            $futureAdmin,
            [
                'name' => 'Future campaign script',
                'code' => 'FUTURE_CAMPAIGN_SCRIPT',
            ]
        );

        $this->actingAs($admin)
            ->postJson(
                '/api/call-scripts',
                $this->validScriptPayload([
                    'tenant_id' => $futureAdmin->tenant_id,
                    'created_by_user_id' => $futureAdmin->id,
                    'status' => 'active',
                    'activated_at' => now()->toISOString(),
                ])
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'tenant_id',
                'created_by_user_id',
                'status',
                'activated_at',
            ]);

        $this->actingAs($admin)
            ->postJson(
                '/api/call-queues',
                $this->validQueuePayload(
                    $futureScript
                )
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'call_script_id',
            ]);

        $ownScript = $this->createScript($admin);
        $ownQueue = $this->createQueue(
            $admin,
            [
                'call_script_id' => $ownScript->id,
            ]
        );

        $futureContact = $this->createContact(
            $futureAdmin
        );

        $this->actingAs($admin)
            ->postJson(
                "/api/call-queues/{$ownQueue->id}/assign",
                [
                    'contact_ids' => [
                        $futureContact->id,
                    ],
                    'tenant_id' => $futureAdmin->tenant_id,
                    'status' => 'completed',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'contact_ids.0',
                'tenant_id',
                'status',
            ]);

        $assignment = $this->createAssignment($admin);

        $this->actingAs($admin)
            ->patchJson(
                "/api/call-assignments/{$assignment->id}",
                [
                    'tenant_id' => $futureAdmin->tenant_id,
                    'contact_id' => $futureContact->id,
                    'completed_at' => now()->toISOString(),
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'tenant_id',
                'contact_id',
                'completed_at',
            ]);

        $this->actingAs($admin)
            ->postJson(
                '/api/call-attempts',
                [
                    'call_assignment_id' => $assignment->id,
                    'client_uuid' => Str::uuid()->toString(),
                    'outcome' => 'callback_requested',
                    'attempted_at' => now()->toISOString(),
                    'tenant_id' => $futureAdmin->tenant_id,
                    'performed_by_user_id' => (
                        $futureAdmin->id
                    ),
                    'reference_code' => 'FORGED-CALL',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'follow_up_at',
                'tenant_id',
                'performed_by_user_id',
                'reference_code',
            ]);

        $this->actingAs($admin)
            ->getJson('/api/call-scripts?status=invalid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->actingAs($admin)
            ->getJson('/api/call-queues?priority=invalid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('priority');

        $this->actingAs($admin)
            ->getJson('/api/call-assignments?status=invalid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->actingAs($admin)
            ->getJson('/api/call-attempts?outcome=invalid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('outcome');

        $this->actingAs($admin)
            ->getJson(
                "/api/call-scripts/{$futureScript->id}"
            )
            ->assertNotFound();
    }

    private function validScriptPayload(
        array $overrides = []
    ): array {
        return array_merge([
            'name' => 'Volunteer confirmation script',
            'code' => 'VOLUNTEER_CONFIRMATION',
            'language_code' => 'en',
            'description' => (
                'Confirm volunteer availability.'
            ),
            'body' => (
                'Introduce the campaign and confirm availability.'
            ),
        ], $overrides);
    }

    private function validQueuePayload(
        CallScript $script,
        array $overrides = []
    ): array {
        return array_merge([
            'call_script_id' => $script->id,
            'name' => 'Volunteer confirmation queue',
            'code' => 'VOLUNTEER_QUEUE',
            'description' => (
                'Contacts awaiting confirmation calls.'
            ),
            'priority' => 'normal',
            'status' => 'active',
            'starts_at' => now()->toISOString(),
            'ends_at' => now()
                ->addDay()
                ->toISOString(),
        ], $overrides);
    }

    private function createScript(
        User $actor,
        array $overrides = []
    ): CallScript {
        $this->actingAs($actor);

        return CallScript::create(array_merge([
            'created_by_user_id' => $actor->id,
            'name' => 'Volunteer confirmation script',
            'code' => 'SCRIPT-'.Str::upper(
                Str::random(10)
            ),
            'language_code' => 'en',
            'description' => 'Fictional call script.',
            'body' => (
                'Confirm the volunteer availability.'
            ),
            'status' => 'active',
        ], $overrides));
    }

    private function createQueue(
        User $actor,
        array $overrides = []
    ): CallQueue {
        $this->actingAs($actor);

        if (! array_key_exists(
            'call_script_id',
            $overrides
        )) {
            $overrides['call_script_id'] = (
                $this->createScript($actor)->id
            );
        }

        return CallQueue::create(array_merge([
            'created_by_user_id' => $actor->id,
            'name' => 'Volunteer confirmation queue',
            'code' => 'QUEUE-'.Str::upper(
                Str::random(10)
            ),
            'description' => 'Fictional calling queue.',
            'priority' => 'normal',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDay(),
        ], $overrides));
    }

    private function createContact(
        User $actor,
        array $overrides = []
    ): Contact {
        $this->actingAs($actor);

        return Contact::create(array_merge([
            'created_by_user_id' => $actor->id,
            'reference_code' => (
                'CALL-CONTACT-'.Str::upper(
                    Str::random(10)
                )
            ),
            'first_name' => 'Maya',
            'last_name' => 'Haddad',
            'phone' => (
                '+96170'.random_int(
                    100000,
                    999999
                )
            ),
            'status' => 'active',
            'source' => 'call_center_api_test',
        ], $overrides));
    }

    private function createAssignment(
        User $actor,
        array $overrides = []
    ): CallAssignment {
        $this->actingAs($actor);

        if (! array_key_exists(
            'call_queue_id',
            $overrides
        )) {
            $overrides['call_queue_id'] = (
                $this->createQueue($actor)->id
            );
        }

        if (! array_key_exists(
            'contact_id',
            $overrides
        )) {
            $overrides['contact_id'] = (
                $this->createContact($actor)->id
            );
        }

        return CallAssignment::create(array_merge([
            'assigned_to_user_id' => $actor->id,
            'assigned_by_user_id' => $actor->id,
            'status' => 'pending',
            'priority' => 'normal',
            'scheduled_for' => now()->addHour(),
            'notes' => 'Fictional call assignment.',
        ], $overrides));
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
