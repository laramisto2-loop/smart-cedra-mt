<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\CampaignTask;
use App\Models\Incident;
use App\Models\IncidentAttachment;
use App\Models\PollingCenter;
use App\Models\PollingStation;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\GeographySeeder;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class IncidentFoundationTest extends TestCase
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

    public function test_incident_relationships_offline_identity_and_workflow_work(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $coordinator = $this->createUserWithRole(
            $admin->tenant,
            'coordinator'
        );
        $fieldAgent = $this->createUserWithRole(
            $admin->tenant,
            'field_agent'
        );

        [$area, $pollingCenter, $pollingStation] =
            $this->findGeography($admin);

        $task = $this->createTask(
            $admin,
            $fieldAgent,
            $area
        );

        $clientUuid = Str::uuid()->toString();

        $incident = $this->createIncident(
            $fieldAgent,
            [
                'assigned_to_user_id' => $coordinator->id,
                'campaign_task_id' => $task->id,
                'area_id' => $area->id,
                'polling_center_id' => $pollingCenter->id,
                'polling_station_id' => $pollingStation->id,
                'client_uuid' => $clientUuid,
            ]
        );

        $attachment = $this->createAttachment(
            $fieldAgent,
            $incident
        );

        $incident->refresh();
        $attachment->refresh();

        $this->assertSame($admin->tenant_id, $incident->tenant_id);
        $this->assertSame($clientUuid, $incident->client_uuid);
        $this->assertMatchesRegularExpression(
            '/^INC-[A-F0-9]{12}$/',
            $incident->reference_code
        );
        $this->assertSame(1, $incident->sync_version);

        $this->assertTrue($incident->tenant->is($admin->tenant));
        $this->assertTrue($incident->reporter->is($fieldAgent));
        $this->assertTrue($incident->assignee->is($coordinator));
        $this->assertTrue($incident->campaignTask->is($task));
        $this->assertTrue($incident->area->is($area));
        $this->assertTrue(
            $incident->pollingCenter->is($pollingCenter)
        );
        $this->assertTrue(
            $incident->pollingStation->is($pollingStation)
        );
        $this->assertTrue(
            $incident->attachments->firstOrFail()->is($attachment)
        );

        $this->assertTrue(
            $admin->tenant->incidents()
                ->firstOrFail()
                ->is($incident)
        );

        $this->assertTrue(
            $admin->tenant->incidentAttachments()
                ->firstOrFail()
                ->is($attachment)
        );

        $this->assertTrue(
            $fieldAgent->reportedIncidents()
                ->firstOrFail()
                ->is($incident)
        );

        $this->assertTrue(
            $coordinator->assignedIncidents()
                ->firstOrFail()
                ->is($incident)
        );

        $this->assertTrue(
            $fieldAgent->uploadedIncidentAttachments()
                ->firstOrFail()
                ->is($attachment)
        );

        $this->assertTrue(
            $task->incidents()
                ->firstOrFail()
                ->is($incident)
        );

        $this->assertTrue(
            $area->incidents()
                ->firstOrFail()
                ->is($incident)
        );

        $this->assertTrue(
            $pollingCenter->incidents()
                ->firstOrFail()
                ->is($incident)
        );

        $this->assertTrue(
            $pollingStation->incidents()
                ->firstOrFail()
                ->is($incident)
        );

        $this->assertTrue($attachment->tenant->is($admin->tenant));
        $this->assertTrue($attachment->incident->is($incident));
        $this->assertTrue($attachment->uploader->is($fieldAgent));
        $this->assertNotNull($attachment->client_uuid);

        $this->assertNull($incident->reviewed_at);
        $this->assertNull($incident->resolved_at);

        $this->actingAs($coordinator);

        $incident->update([
            'status' => 'in_review',
            'reviewed_by_user_id' => $coordinator->id,
        ]);
        $incident->refresh();

        $this->assertNotNull($incident->reviewed_at);
        $this->assertNull($incident->resolved_at);
        $this->assertTrue($incident->reviewer->is($coordinator));
        $this->assertSame(2, $incident->sync_version);

        $this->assertTrue(
            $coordinator->reviewedIncidents()
                ->firstOrFail()
                ->is($incident)
        );

        $incident->update([
            'status' => 'resolved',
            'resolution_notes' => 'Resolved during foundation testing.',
        ]);
        $incident->refresh();

        $this->assertNotNull($incident->resolved_at);
        $this->assertSame(3, $incident->sync_version);
    }

    public function test_tenant_only_queries_its_own_incidents_and_attachments(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $cedraIncident = $this->createIncident($cedraAdmin);
        $futureIncident = $this->createIncident($futureAdmin);

        $cedraAttachment = $this->createAttachment(
            $cedraAdmin,
            $cedraIncident
        );

        $this->createAttachment(
            $futureAdmin,
            $futureIncident
        );

        $this->actingAs($cedraAdmin);

        $this->assertCount(1, Incident::all());
        $this->assertTrue(
            Incident::firstOrFail()->is($cedraIncident)
        );
        $this->assertNull(Incident::find($futureIncident->id));

        $this->assertCount(1, IncidentAttachment::all());
        $this->assertTrue(
            IncidentAttachment::firstOrFail()->is($cedraAttachment)
        );

        $this->assertSame(
            2,
            Incident::withoutGlobalScopes()->count()
        );

        $this->assertSame(
            2,
            IncidentAttachment::withoutGlobalScopes()->count()
        );
    }

    public function test_active_tenant_overrides_submitted_tenant_id(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $incident = $this->createIncident(
            $cedraAdmin,
            ['tenant_id' => $futureAdmin->tenant_id]
        );

        $attachment = $this->createAttachment(
            $cedraAdmin,
            $incident,
            ['tenant_id' => $futureAdmin->tenant_id]
        );

        $this->assertSame(
            $cedraAdmin->tenant_id,
            $incident->tenant_id
        );

        $this->assertSame(
            $cedraAdmin->tenant_id,
            $attachment->tenant_id
        );
    }

    public function test_incident_rejects_cross_tenant_relationships(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        [$futureArea, $futureCenter, $futureStation] =
            $this->findGeography($futureAdmin);

        $futureTask = $this->createTask(
            $futureAdmin,
            $futureAdmin,
            $futureArea
        );

        $this->assertIncidentCreationFails(
            $cedraAdmin,
            ['reported_by_user_id' => $futureAdmin->id],
            'The incident reporter must belong to the same tenant.'
        );

        $this->assertIncidentCreationFails(
            $cedraAdmin,
            ['assigned_to_user_id' => $futureAdmin->id],
            'The incident assignee must belong to the same tenant.'
        );

        $this->assertIncidentCreationFails(
            $cedraAdmin,
            ['reviewed_by_user_id' => $futureAdmin->id],
            'The incident reviewer must belong to the same tenant.'
        );

        $this->assertIncidentCreationFails(
            $cedraAdmin,
            ['campaign_task_id' => $futureTask->id],
            'The incident campaign task must belong to the same tenant.'
        );

        $this->assertIncidentCreationFails(
            $cedraAdmin,
            ['area_id' => $futureArea->id],
            'The incident area must belong to the same tenant.'
        );

        $this->assertIncidentCreationFails(
            $cedraAdmin,
            ['polling_center_id' => $futureCenter->id],
            'The incident polling center must belong to the same tenant.'
        );

        $this->assertIncidentCreationFails(
            $cedraAdmin,
            ['polling_station_id' => $futureStation->id],
            'The incident polling station must belong to the same tenant.'
        );
    }

    public function test_incident_rejects_inconsistent_geography(): void
    {
        $admin = $this->findUser('admin@cedra.test');

        [$area, $pollingCenter, $pollingStation] =
            $this->findGeography($admin);

        $this->actingAs($admin);

        $otherArea = Area::create([
            'district_id' => $area->district_id,
            'name_en' => 'Other Test Area',
            'name_ar' => 'Other Test Area',
            'code' => 'INCIDENT-OTHER-AREA',
            'type' => 'neighbourhood',
        ]);

        $otherCenter = PollingCenter::create([
            'area_id' => $area->id,
            'name_en' => 'Other Test Center',
            'name_ar' => 'Other Test Center',
            'code' => 'INCIDENT-OTHER-CENTER',
        ]);

        $this->assertIncidentCreationFails(
            $admin,
            [
                'area_id' => $otherArea->id,
                'polling_center_id' => $pollingCenter->id,
            ],
            'The incident polling center must belong to the selected area.'
        );

        $this->assertIncidentCreationFails(
            $admin,
            [
                'polling_center_id' => $otherCenter->id,
                'polling_station_id' => $pollingStation->id,
            ],
            'The incident polling station must belong to the selected polling center.'
        );

        $this->assertIncidentCreationFails(
            $admin,
            [
                'area_id' => $otherArea->id,
                'polling_station_id' => $pollingStation->id,
            ],
            'The incident polling station must belong to the selected area.'
        );
    }

    public function test_attachment_rejects_cross_tenant_relationships(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $cedraIncident = $this->createIncident($cedraAdmin);
        $futureIncident = $this->createIncident($futureAdmin);

        $this->assertAttachmentCreationFails(
            $cedraAdmin,
            $futureIncident,
            [],
            'The attachment incident must belong to the same tenant.'
        );

        $this->assertAttachmentCreationFails(
            $cedraAdmin,
            $cedraIncident,
            ['uploaded_by_user_id' => $futureAdmin->id],
            'The attachment uploader must belong to the same tenant.'
        );
    }

    public function test_incident_policy_enforces_roles_ownership_status_and_tenants(): void
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

        $ownIncident = $this->createIncident($fieldAgent);

        $assignedIncident = $this->createIncident(
            $otherFieldAgent,
            ['assigned_to_user_id' => $fieldAgent->id]
        );

        $otherIncident = $this->createIncident($otherFieldAgent);
        $otherTenantIncident = $this->createIncident($futureAdmin);

        $this->actingAs($admin);
        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'viewAny',
                Incident::class
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows('view', $ownIncident)
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'create',
                Incident::class
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows('update', $ownIncident)
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows('assign', $ownIncident)
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows('review', $ownIncident)
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'manageAttachments',
                $ownIncident
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows('delete', $ownIncident)
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'viewAny',
                Incident::class
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'view',
                $otherIncident
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'create',
                Incident::class
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'update',
                $otherIncident
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'assign',
                $otherIncident
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'review',
                $otherIncident
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'manageAttachments',
                $otherIncident
            )
        );

        $this->assertFalse(
            Gate::forUser($coordinator)->allows(
                'delete',
                $otherIncident
            )
        );

        $this->assertTrue(
            Gate::forUser($fieldAgent)->allows(
                'viewAny',
                Incident::class
            )
        );

        $this->assertTrue(
            Gate::forUser($fieldAgent)->allows(
                'create',
                Incident::class
            )
        );

        $this->assertTrue(
            Gate::forUser($fieldAgent)->allows(
                'view',
                $ownIncident
            )
        );

        $this->assertTrue(
            Gate::forUser($fieldAgent)->allows(
                'update',
                $ownIncident
            )
        );

        $this->assertTrue(
            Gate::forUser($fieldAgent)->allows(
                'manageAttachments',
                $ownIncident
            )
        );

        $this->assertTrue(
            Gate::forUser($fieldAgent)->allows(
                'view',
                $assignedIncident
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'update',
                $assignedIncident
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'manageAttachments',
                $assignedIncident
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'view',
                $otherIncident
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'assign',
                $ownIncident
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'review',
                $ownIncident
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'delete',
                $ownIncident
            )
        );

        $this->actingAs($coordinator);

        $ownIncident->update([
            'status' => 'in_review',
            'reviewed_by_user_id' => $coordinator->id,
        ]);
        $ownIncident->refresh();

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'update',
                $ownIncident
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'manageAttachments',
                $ownIncident
            )
        );

        foreach (
            [
                'view',
                'update',
                'assign',
                'review',
                'manageAttachments',
                'delete',
            ] as $ability
        ) {
            $this->assertFalse(
                Gate::forUser($admin)->allows(
                    $ability,
                    $otherTenantIncident
                )
            );
        }
    }

    private function createIncident(
        User $reporter,
        array $overrides = []
    ): Incident {
        $this->actingAs($reporter);

        return Incident::create(array_merge([
            'reported_by_user_id' => $reporter->id,
            'title' => 'Foundation test incident',
            'description' => 'A fictional operational incident.',
            'category' => 'safety',
            'severity' => 'high',
            'status' => 'submitted',
            'location_notes' => 'Fictional test location.',
            'occurred_at' => now()->subMinutes(10),
            'client_updated_at' => now()->subMinute(),
        ], $overrides));
    }

    private function createAttachment(
        User $uploader,
        Incident $incident,
        array $overrides = []
    ): IncidentAttachment {
        $this->actingAs($uploader);

        return IncidentAttachment::create(array_merge([
            'incident_id' => $incident->id,
            'uploaded_by_user_id' => $uploader->id,
            'disk' => 'local',
            'path' => 'incidents/test-evidence.jpg',
            'original_name' => 'test-evidence.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'checksum_sha256' => str_repeat('a', 64),
            'captured_at' => now()->subMinutes(5),
            'client_updated_at' => now()->subMinute(),
        ], $overrides));
    }

    private function createTask(
        User $creator,
        ?User $assignee = null,
        ?Area $area = null
    ): CampaignTask {
        $this->actingAs($creator);

        return CampaignTask::create([
            'area_id' => $area?->id,
            'created_by_user_id' => $creator->id,
            'assigned_to_user_id' => $assignee?->id,
            'title' => 'Incident foundation task',
            'description' => 'A fictional field assignment.',
            'type' => 'field_visit',
            'priority' => 'high',
            'status' => 'pending',
        ]);
    }

    private function assertIncidentCreationFails(
        User $actor,
        array $overrides,
        string $expectedMessage
    ): void {
        try {
            $this->createIncident($actor, $overrides);

            $this->fail(
                'The invalid incident relationship should have been rejected.'
            );
        } catch (LogicException $exception) {
            $this->assertSame(
                $expectedMessage,
                $exception->getMessage()
            );
        }
    }

    private function assertAttachmentCreationFails(
        User $actor,
        Incident $incident,
        array $overrides,
        string $expectedMessage
    ): void {
        try {
            $this->createAttachment(
                $actor,
                $incident,
                $overrides
            );

            $this->fail(
                'The invalid attachment relationship should have been rejected.'
            );
        } catch (LogicException $exception) {
            $this->assertSame(
                $expectedMessage,
                $exception->getMessage()
            );
        }
    }

    /**
     * @return array{Area, PollingCenter, PollingStation}
     */
    private function findGeography(User $user): array
    {
        $pollingStation = PollingStation::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->firstOrFail();

        $pollingCenter = PollingCenter::withoutGlobalScopes()
            ->whereKey($pollingStation->polling_center_id)
            ->firstOrFail();

        $area = Area::withoutGlobalScopes()
            ->whereKey($pollingCenter->area_id)
            ->firstOrFail();

        return [
            $area,
            $pollingCenter,
            $pollingStation,
        ];
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

// It covers tenant isolation, relationships, workflow timestamps, sync versions, offline UUID behavior, geography consistency, attachment ownership, and all three roles
