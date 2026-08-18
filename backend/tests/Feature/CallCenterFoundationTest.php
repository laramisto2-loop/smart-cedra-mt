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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class CallCenterFoundationTest extends TestCase
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

    public function test_call_center_relationships_and_workflow_identity_work(): void
    {
        $admin = $this->findUser('admin@cedra.test');

        $agent = $this->createUserWithRole(
            $admin->tenant,
            'field_agent'
        );

        $script = $this->createScript($admin);
        $queue = $this->createQueue($admin, [
            'call_script_id' => $script->id,
        ]);

        $contact = $this->createContact($admin);

        $assignment = $this->createAssignment($admin, [
            'call_queue_id' => $queue->id,
            'contact_id' => $contact->id,
            'assigned_to_user_id' => $agent->id,
            'assigned_by_user_id' => $admin->id,
            'status' => 'in_progress',
        ]);

        $task = $this->createTask(
            $admin,
            $agent,
            $contact
        );

        $clientUuid = Str::uuid()->toString();

        $attempt = $this->createAttempt(
            $agent,
            $assignment,
            [
                'follow_up_task_id' => $task->id,
                'client_uuid' => $clientUuid,
            ]
        );

        $script->refresh();
        $queue->refresh();
        $assignment->refresh();
        $attempt->refresh();

        $this->assertSame(
            $admin->tenant_id,
            $script->tenant_id
        );

        $this->assertSame(
            $admin->tenant_id,
            $queue->tenant_id
        );

        $this->assertSame(
            $admin->tenant_id,
            $assignment->tenant_id
        );

        $this->assertSame(
            $admin->tenant_id,
            $attempt->tenant_id
        );

        $this->assertNotNull($script->activated_at);
        $this->assertNotNull($assignment->claimed_at);
        $this->assertNotNull($attempt->attempted_at);

        $this->assertSame($clientUuid, $attempt->client_uuid);

        $this->assertMatchesRegularExpression(
            '/^CALL-[A-F0-9]{12}$/',
            $attempt->reference_code
        );

        $this->assertTrue($script->tenant->is($admin->tenant));
        $this->assertTrue($script->creator->is($admin));

        $this->assertTrue($queue->tenant->is($admin->tenant));
        $this->assertTrue($queue->callScript->is($script));
        $this->assertTrue($queue->creator->is($admin));

        $this->assertTrue(
            $assignment->callQueue->is($queue)
        );

        $this->assertTrue(
            $assignment->contact->is($contact)
        );

        $this->assertTrue(
            $assignment->assignee->is($agent)
        );

        $this->assertTrue(
            $assignment->assigner->is($admin)
        );

        $this->assertTrue(
            $attempt->callAssignment->is($assignment)
        );

        $this->assertTrue($attempt->performer->is($agent));
        $this->assertTrue($attempt->followUpTask->is($task));

        $this->assertTrue(
            $script->queues()->firstOrFail()->is($queue)
        );

        $this->assertTrue(
            $queue->assignments()
                ->firstOrFail()
                ->is($assignment)
        );

        $this->assertTrue(
            $assignment->attempts()
                ->firstOrFail()
                ->is($attempt)
        );

        $this->assertTrue(
            $contact->callAssignments()
                ->firstOrFail()
                ->is($assignment)
        );

        $this->assertTrue(
            $agent->callAssignments()
                ->firstOrFail()
                ->is($assignment)
        );

        $this->assertTrue(
            $admin->assignedCallAssignments()
                ->firstOrFail()
                ->is($assignment)
        );

        $this->assertTrue(
            $agent->performedCallAttempts()
                ->firstOrFail()
                ->is($attempt)
        );

        $this->assertTrue(
            $task->callAttempts()
                ->firstOrFail()
                ->is($attempt)
        );

        $this->assertTrue(
            $admin->tenant->callScripts()
                ->firstOrFail()
                ->is($script)
        );

        $this->assertTrue(
            $admin->tenant->callQueues()
                ->firstOrFail()
                ->is($queue)
        );

        $this->assertTrue(
            $admin->tenant->callAssignments()
                ->firstOrFail()
                ->is($assignment)
        );

        $this->assertTrue(
            $admin->tenant->callAttempts()
                ->firstOrFail()
                ->is($attempt)
        );
    }

    public function test_tenant_only_queries_its_own_call_center_records(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $cedraScript = $this->createScript($cedraAdmin);
        $cedraQueue = $this->createQueue($cedraAdmin, [
            'call_script_id' => $cedraScript->id,
        ]);

        $cedraAssignment = $this->createAssignment(
            $cedraAdmin,
            ['call_queue_id' => $cedraQueue->id]
        );

        $cedraAttempt = $this->createAttempt(
            $cedraAdmin,
            $cedraAssignment
        );

        $futureScript = $this->createScript($futureAdmin);
        $futureQueue = $this->createQueue($futureAdmin, [
            'call_script_id' => $futureScript->id,
        ]);

        $futureAssignment = $this->createAssignment(
            $futureAdmin,
            ['call_queue_id' => $futureQueue->id]
        );

        $futureAttempt = $this->createAttempt(
            $futureAdmin,
            $futureAssignment
        );

        $this->actingAs($cedraAdmin);

        $this->assertCount(1, CallScript::all());
        $this->assertCount(1, CallQueue::all());
        $this->assertCount(1, CallAssignment::all());
        $this->assertCount(1, CallAttempt::all());

        $this->assertTrue(
            CallScript::firstOrFail()->is($cedraScript)
        );

        $this->assertTrue(
            CallQueue::firstOrFail()->is($cedraQueue)
        );

        $this->assertTrue(
            CallAssignment::firstOrFail()->is($cedraAssignment)
        );

        $this->assertTrue(
            CallAttempt::firstOrFail()->is($cedraAttempt)
        );

        $this->assertNull(CallScript::find($futureScript->id));
        $this->assertNull(CallQueue::find($futureQueue->id));

        $this->assertNull(
            CallAssignment::find($futureAssignment->id)
        );

        $this->assertNull(
            CallAttempt::find($futureAttempt->id)
        );

        $this->assertSame(
            2,
            CallScript::withoutGlobalScopes()->count()
        );

        $this->assertSame(
            2,
            CallQueue::withoutGlobalScopes()->count()
        );

        $this->assertSame(
            2,
            CallAssignment::withoutGlobalScopes()->count()
        );

        $this->assertSame(
            2,
            CallAttempt::withoutGlobalScopes()->count()
        );
    }

    public function test_active_tenant_overrides_submitted_tenant_id(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $script = $this->createScript($cedraAdmin, [
            'tenant_id' => $futureAdmin->tenant_id,
        ]);

        $queue = $this->createQueue($cedraAdmin, [
            'tenant_id' => $futureAdmin->tenant_id,
            'call_script_id' => $script->id,
        ]);

        $assignment = $this->createAssignment($cedraAdmin, [
            'tenant_id' => $futureAdmin->tenant_id,
            'call_queue_id' => $queue->id,
        ]);

        $attempt = $this->createAttempt(
            $cedraAdmin,
            $assignment,
            ['tenant_id' => $futureAdmin->tenant_id]
        );

        $this->assertSame(
            $cedraAdmin->tenant_id,
            $script->tenant_id
        );

        $this->assertSame(
            $cedraAdmin->tenant_id,
            $queue->tenant_id
        );

        $this->assertSame(
            $cedraAdmin->tenant_id,
            $assignment->tenant_id
        );

        $this->assertSame(
            $cedraAdmin->tenant_id,
            $attempt->tenant_id
        );
    }

    public function test_call_center_models_reject_cross_tenant_relationships(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $cedraScript = $this->createScript($cedraAdmin);
        $cedraQueue = $this->createQueue($cedraAdmin, [
            'call_script_id' => $cedraScript->id,
        ]);

        $cedraContact = $this->createContact($cedraAdmin);

        $cedraAssignment = $this->createAssignment(
            $cedraAdmin,
            [
                'call_queue_id' => $cedraQueue->id,
                'contact_id' => $cedraContact->id,
            ]
        );

        $futureScript = $this->createScript($futureAdmin);
        $futureQueue = $this->createQueue($futureAdmin, [
            'call_script_id' => $futureScript->id,
        ]);

        $futureContact = $this->createContact($futureAdmin);

        $futureAssignment = $this->createAssignment(
            $futureAdmin,
            [
                'call_queue_id' => $futureQueue->id,
                'contact_id' => $futureContact->id,
            ]
        );

        $futureTask = $this->createTask(
            $futureAdmin,
            $futureAdmin,
            $futureContact
        );

        $this->assertScriptCreationFails(
            $cedraAdmin,
            ['created_by_user_id' => $futureAdmin->id],
            'The call script creator must belong to the same tenant.'
        );

        $this->assertQueueCreationFails(
            $cedraAdmin,
            ['created_by_user_id' => $futureAdmin->id],
            'The call queue creator must belong to the same tenant.'
        );

        $this->assertQueueCreationFails(
            $cedraAdmin,
            ['call_script_id' => $futureScript->id],
            'The call script must belong to the same tenant.'
        );

        $this->assertAssignmentCreationFails(
            $cedraAdmin,
            ['call_queue_id' => $futureQueue->id],
            'The call queue must belong to the same tenant.'
        );

        $this->assertAssignmentCreationFails(
            $cedraAdmin,
            ['contact_id' => $futureContact->id],
            'The contact must belong to the same tenant.'
        );

        $this->assertAssignmentCreationFails(
            $cedraAdmin,
            ['assigned_to_user_id' => $futureAdmin->id],
            'The assigned agent must belong to the same tenant.'
        );

        $this->assertAssignmentCreationFails(
            $cedraAdmin,
            ['assigned_by_user_id' => $futureAdmin->id],
            'The assigning user must belong to the same tenant.'
        );

        $this->assertAttemptCreationFails(
            $cedraAdmin,
            $futureAssignment,
            [],
            'The call attempt assignment must belong to the same tenant.'
        );

        $this->assertAttemptCreationFails(
            $cedraAdmin,
            $cedraAssignment,
            ['performed_by_user_id' => $futureAdmin->id],
            'The call attempt agent must belong to the same tenant.'
        );

        $this->assertAttemptCreationFails(
            $cedraAdmin,
            $cedraAssignment,
            ['follow_up_task_id' => $futureTask->id],
            'The call attempt follow-up task must belong to the same tenant.'
        );
    }

    public function test_call_center_workflow_validation_is_enforced(): void
    {
        $admin = $this->findUser('admin@cedra.test');

        $this->assertScriptCreationFails(
            $admin,
            ['status' => 'invalid-status'],
            'The call script status is invalid.'
        );

        $draftScript = $this->createScript($admin, [
            'status' => 'draft',
            'activated_at' => now(),
        ]);

        $this->assertNull($draftScript->activated_at);

        $activeScript = $this->createScript($admin);
        $this->assertNotNull($activeScript->activated_at);

        $this->assertQueueCreationFails(
            $admin,
            ['priority' => 'impossible'],
            'The call queue priority is invalid.'
        );

        $this->assertQueueCreationFails(
            $admin,
            ['status' => 'invalid-status'],
            'The call queue status is invalid.'
        );

        $this->assertQueueCreationFails(
            $admin,
            [
                'status' => 'active',
                'call_script_id' => null,
            ],
            'An active call queue must have a call script.'
        );

        $this->assertQueueCreationFails(
            $admin,
            [
                'status' => 'active',
                'call_script_id' => $draftScript->id,
            ],
            'An active call queue must use an active call script.'
        );

        $this->assertQueueCreationFails(
            $admin,
            [
                'starts_at' => now()->addDay(),
                'ends_at' => now(),
            ],
            'The call queue end time cannot be before its start time.'
        );

        $this->assertAssignmentCreationFails(
            $admin,
            ['priority' => 'impossible'],
            'The call assignment priority is invalid.'
        );

        $this->assertAssignmentCreationFails(
            $admin,
            ['status' => 'invalid-status'],
            'The call assignment status is invalid.'
        );

        $this->assertAssignmentCreationFails(
            $admin,
            [
                'status' => 'in_progress',
                'assigned_to_user_id' => null,
            ],
            'An active or completed assignment must have an agent.'
        );

        $completedAssignment = $this->createAssignment($admin, [
            'status' => 'completed',
        ]);

        $this->assertNotNull(
            $completedAssignment->completed_at
        );

        $assignment = $this->createAssignment($admin);

        $this->assertAttemptCreationFails(
            $admin,
            $assignment,
            ['outcome' => 'invalid-outcome'],
            'The call attempt outcome is invalid.'
        );

        $this->assertAttemptCreationFails(
            $admin,
            $assignment,
            ['duration_seconds' => -1],
            'The call duration cannot be negative.'
        );

        $this->assertAttemptCreationFails(
            $admin,
            $assignment,
            [
                'outcome' => 'callback_requested',
                'follow_up_at' => null,
            ],
            'A requested callback must include a follow-up time.'
        );

        $callbackAttempt = $this->createAttempt(
            $admin,
            $assignment,
            [
                'outcome' => 'callback_requested',
                'follow_up_at' => now()->addDay(),
            ]
        );

        $this->assertSame(
            'callback_requested',
            $callbackAttempt->outcome
        );

        $this->assertNotNull($callbackAttempt->follow_up_at);
    }

    public function test_call_attempts_are_immutable_and_uuid_is_unique(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $assignment = $this->createAssignment($admin);
        $clientUuid = Str::uuid()->toString();

        $attempt = $this->createAttempt(
            $admin,
            $assignment,
            ['client_uuid' => $clientUuid]
        );

        try {
            $attempt->update([
                'outcome' => 'no_answer',
            ]);

            $this->fail(
                'A call attempt should not be updated.'
            );
        } catch (LogicException $exception) {
            $this->assertSame(
                'Call attempts are immutable. Record another attempt to correct the call history.',
                $exception->getMessage()
            );
        }

        $attempt->refresh();

        try {
            $attempt->delete();

            $this->fail(
                'A call attempt should not be deleted.'
            );
        } catch (LogicException $exception) {
            $this->assertSame(
                'Call attempts cannot be deleted because they form part of the call history.',
                $exception->getMessage()
            );
        }

        try {
            $this->createAttempt(
                $admin,
                $assignment,
                ['client_uuid' => $clientUuid]
            );

            $this->fail(
                'A duplicate offline UUID should not create another attempt.'
            );
        } catch (QueryException) {
            $this->assertSame(
                1,
                CallAttempt::withoutGlobalScopes()->count()
            );
        }
    }

    public function test_policies_enforce_roles_ownership_status_and_tenants(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $admin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $coordinator = $this->createUserWithRole(
            $tenant,
            'coordinator'
        );

        $fieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $otherFieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $draftScript = $this->createScript($admin, [
            'status' => 'draft',
        ]);

        $activeScript = $this->createScript($admin);

        $activeQueue = $this->createQueue($admin, [
            'call_script_id' => $activeScript->id,
        ]);

        $draftQueue = $this->createQueue($admin, [
            'status' => 'draft',
            'call_script_id' => null,
        ]);

        $unassigned = $this->createAssignment($admin, [
            'call_queue_id' => $activeQueue->id,
            'assigned_to_user_id' => null,
        ]);

        $ownAssignment = $this->createAssignment($admin, [
            'call_queue_id' => $activeQueue->id,
            'assigned_to_user_id' => $fieldAgent->id,
        ]);

        $otherAssignment = $this->createAssignment($admin, [
            'call_queue_id' => $activeQueue->id,
            'assigned_to_user_id' => $otherFieldAgent->id,
        ]);

        $ownAttempt = $this->createAttempt(
            $fieldAgent,
            $ownAssignment
        );

        $otherAttempt = $this->createAttempt(
            $otherFieldAgent,
            $otherAssignment
        );

        $futureScript = $this->createScript($futureAdmin);

        $futureQueue = $this->createQueue($futureAdmin, [
            'call_script_id' => $futureScript->id,
        ]);

        $futureAssignment = $this->createAssignment(
            $futureAdmin,
            ['call_queue_id' => $futureQueue->id]
        );

        $futureAttempt = $this->createAttempt(
            $futureAdmin,
            $futureAssignment
        );

        $this->actingAs($admin);
        $this->actingAs($fieldAgent);

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'viewAny',
                CallScript::class
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'create',
                CallScript::class
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'update',
                $draftScript
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'activate',
                $draftScript
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'delete',
                $draftScript
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'assign',
                $activeQueue
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'delete',
                $draftQueue
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'view',
                $otherAssignment
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'view',
                $otherAttempt
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'create',
                CallScript::class
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'update',
                $draftScript
            )
        );

        $this->assertFalse(
            Gate::forUser($coordinator)->allows(
                'activate',
                $draftScript
            )
        );

        $this->assertFalse(
            Gate::forUser($coordinator)->allows(
                'delete',
                $draftScript
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'assign',
                $activeQueue
            )
        );

        $this->assertFalse(
            Gate::forUser($coordinator)->allows(
                'delete',
                $draftQueue
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'view',
                $otherAssignment
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'view',
                $otherAttempt
            )
        );

        $this->assertTrue(
            Gate::forUser($fieldAgent)->allows(
                'viewAny',
                CallAssignment::class
            )
        );

        $this->assertTrue(
            Gate::forUser($fieldAgent)->allows(
                'view',
                $ownAssignment
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'view',
                $otherAssignment
            )
        );

        $this->assertTrue(
            Gate::forUser($fieldAgent)->allows(
                'claim',
                $unassigned
            )
        );

        $this->assertTrue(
            Gate::forUser($fieldAgent)->allows(
                'update',
                $ownAssignment
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'update',
                $otherAssignment
            )
        );

        $this->assertTrue(
            Gate::forUser($fieldAgent)->allows(
                'view',
                $ownAttempt
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'view',
                $otherAttempt
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'create',
                CallQueue::class
            )
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'view',
                $futureScript
            )
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'view',
                $futureQueue
            )
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'view',
                $futureAssignment
            )
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'view',
                $futureAttempt
            )
        );
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
            'body' => 'Confirm the volunteer availability.',
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
            $overrides['call_script_id'] =
                $this->createScript($actor)->id;
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
            'reference_code' => 'CALL-CONTACT-'.Str::upper(
                Str::random(10)
            ),
            'first_name' => 'Maya',
            'last_name' => 'Haddad',
            'phone' => '+96170'.random_int(100000, 999999),
            'status' => 'active',
            'source' => 'call_center_foundation_test',
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
            $overrides['call_queue_id'] =
                $this->createQueue($actor)->id;
        }

        if (! array_key_exists('contact_id', $overrides)) {
            $overrides['contact_id'] =
                $this->createContact($actor)->id;
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

    private function createTask(
        User $creator,
        ?User $assignee = null,
        ?Contact $contact = null
    ): CampaignTask {
        $this->actingAs($creator);

        return CampaignTask::create([
            'contact_id' => $contact?->id,
            'created_by_user_id' => $creator->id,
            'assigned_to_user_id' => $assignee?->id,
            'title' => 'Call follow-up task',
            'description' => 'Fictional call follow-up.',
            'type' => 'follow_up',
            'priority' => 'high',
            'status' => 'pending',
            'due_at' => now()->addDay(),
        ]);
    }

    private function createAttempt(
        User $actor,
        CallAssignment $assignment,
        array $overrides = []
    ): CallAttempt {
        $this->actingAs($actor);

        return CallAttempt::create(array_merge([
            'call_assignment_id' => $assignment->id,
            'performed_by_user_id' => $actor->id,
            'client_uuid' => Str::uuid()->toString(),
            'outcome' => 'completed',
            'duration_seconds' => 90,
            'notes' => 'Fictional completed call.',
        ], $overrides));
    }

    private function assertScriptCreationFails(
        User $actor,
        array $overrides,
        string $expectedMessage
    ): void {
        try {
            $this->createScript($actor, $overrides);

            $this->fail(
                'The invalid call script should have been rejected.'
            );
        } catch (LogicException $exception) {
            $this->assertSame(
                $expectedMessage,
                $exception->getMessage()
            );
        }
    }

    private function assertQueueCreationFails(
        User $actor,
        array $overrides,
        string $expectedMessage
    ): void {
        try {
            $this->createQueue($actor, $overrides);

            $this->fail(
                'The invalid call queue should have been rejected.'
            );
        } catch (LogicException $exception) {
            $this->assertSame(
                $expectedMessage,
                $exception->getMessage()
            );
        }
    }

    private function assertAssignmentCreationFails(
        User $actor,
        array $overrides,
        string $expectedMessage
    ): void {
        try {
            $this->createAssignment($actor, $overrides);

            $this->fail(
                'The invalid call assignment should have been rejected.'
            );
        } catch (LogicException $exception) {
            $this->assertSame(
                $expectedMessage,
                $exception->getMessage()
            );
        }
    }

    private function assertAttemptCreationFails(
        User $actor,
        CallAssignment $assignment,
        array $overrides,
        string $expectedMessage
    ): void {
        try {
            $this->createAttempt(
                $actor,
                $assignment,
                $overrides
            );

            $this->fail(
                'The invalid call attempt should have been rejected.'
            );
        } catch (LogicException $exception) {
            $this->assertSame(
                $expectedMessage,
                $exception->getMessage()
            );
        }
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
