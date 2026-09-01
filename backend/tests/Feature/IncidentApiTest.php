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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class IncidentApiTest extends TestCase
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

        Storage::fake('local');
    }

    public function test_unauthenticated_user_cannot_access_incident_api(): void
    {
        $this->getJson('/api/incidents')
            ->assertUnauthorized();

        $this->postJson('/api/incidents', [])
            ->assertUnauthorized();
    }

    public function test_admin_only_receives_and_filters_own_tenant_incidents(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $matching = $this->createIncident($cedraAdmin, [
            'title' => 'Critical access issue',
            'severity' => 'critical',
        ]);

        $this->createIncident($cedraAdmin, [
            'title' => 'Routine logistics note',
            'severity' => 'low',
        ]);

        $futureIncident = $this->createIncident($futureAdmin, [
            'title' => 'Critical future issue',
            'severity' => 'critical',
        ]);

        $this->actingAs($cedraAdmin)
            ->getJson(
                '/api/incidents?search=Critical&severity=critical'
            )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonMissing([
                'id' => $futureIncident->id,
            ]);
    }

    public function test_field_agent_can_submit_an_idempotent_offline_incident(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $admin = $this->findUser('admin@cedra.test');

        $fieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        [$area, $center, $station] = $this->findGeography(
            $tenant
        );

        $task = $this->createTask(
            $admin,
            $fieldAgent,
            $area
        );

        $clientUuid = Str::uuid()->toString();

        $payload = $this->validIncidentPayload([
            'campaign_task_id' => $task->id,
            'area_id' => $area->id,
            'polling_center_id' => $center->id,
            'polling_station_id' => $station->id,
            'client_uuid' => $clientUuid,
        ]);

        $this->actingAs($fieldAgent)
            ->postJson('/api/incidents', $payload)
            ->assertCreated()
            ->assertJsonPath('data.client_uuid', $clientUuid)
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath(
                'data.reported_by_user_id',
                $fieldAgent->id
            )
            ->assertJsonPath('data.sync_version', 1);

        $this->actingAs($fieldAgent)
            ->postJson('/api/incidents', $payload)
            ->assertOk()
            ->assertJsonPath('data.client_uuid', $clientUuid);

        $this->assertDatabaseCount('incidents', 1);

        $this->assertDatabaseHas('incidents', [
            'tenant_id' => $tenant->id,
            'reported_by_user_id' => $fieldAgent->id,
            'campaign_task_id' => $task->id,
            'client_uuid' => $clientUuid,
            'status' => 'submitted',
        ]);
    }

    public function test_incident_validation_protects_internal_and_tenant_fields(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');
        $futureTenant = $this->findTenant('lebanon-future');

        [$futureArea] = $this->findGeography($futureTenant);

        $futureTask = $this->createTask(
            $futureAdmin,
            $futureAdmin,
            $futureArea
        );

        $this->actingAs($cedraAdmin)
            ->postJson(
                '/api/incidents',
                $this->validIncidentPayload([
                    'tenant_id' => $futureTenant->id,
                    'reported_by_user_id' => $futureAdmin->id,
                    'assigned_to_user_id' => $futureAdmin->id,
                    'reviewed_by_user_id' => $futureAdmin->id,
                    'reference_code' => 'FORGED-INCIDENT',
                    'status' => 'resolved',
                    'reported_at' => now()->toISOString(),
                    'reviewed_at' => now()->toISOString(),
                    'resolved_at' => now()->toISOString(),
                    'resolution_notes' => 'Forged resolution.',
                    'sync_version' => 99,
                ])
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'tenant_id',
                'reported_by_user_id',
                'assigned_to_user_id',
                'reviewed_by_user_id',
                'reference_code',
                'status',
                'reported_at',
                'reviewed_at',
                'resolved_at',
                'resolution_notes',
                'sync_version',
            ]);

        $this->actingAs($cedraAdmin)
            ->postJson(
                '/api/incidents',
                $this->validIncidentPayload([
                    'campaign_task_id' => $futureTask->id,
                    'area_id' => $futureArea->id,
                ])
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'campaign_task_id',
                'area_id',
            ]);

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_field_agent_only_accesses_owned_or_assigned_incidents(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $fieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $otherAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $ownIncident = $this->createIncident($fieldAgent);

        $assignedIncident = $this->createIncident(
            $otherAgent,
            ['assigned_to_user_id' => $fieldAgent->id]
        );

        $unrelatedIncident = $this->createIncident($otherAgent);

        $response = $this->actingAs($fieldAgent)
            ->getJson('/api/incidents')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $incidentIds = collect($response->json('data'))
            ->pluck('id')
            ->all();

        $this->assertContains($ownIncident->id, $incidentIds);
        $this->assertContains($assignedIncident->id, $incidentIds);
        $this->assertNotContains($unrelatedIncident->id, $incidentIds);

        $this->actingAs($fieldAgent)
            ->patchJson(
                "/api/incidents/{$ownIncident->id}",
                [
                    'title' => 'Updated offline incident',
                    'expected_sync_version' => 1,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.title',
                'Updated offline incident'
            )
            ->assertJsonPath('data.sync_version', 2);

        $this->actingAs($fieldAgent)
            ->patchJson(
                "/api/incidents/{$ownIncident->id}",
                [
                    'title' => 'Stale overwrite attempt',
                    'expected_sync_version' => 1,
                ]
            )
            ->assertStatus(409);

        $this->actingAs($fieldAgent)
            ->patchJson(
                "/api/incidents/{$assignedIncident->id}",
                ['title' => 'Forbidden update']
            )
            ->assertForbidden();
    }

    public function test_coordinator_can_assign_review_and_resolve_incidents(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $admin = $this->findUser('admin@cedra.test');

        $coordinator = $this->createUserWithRole(
            $tenant,
            'coordinator'
        );

        $fieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $incident = $this->createIncident($fieldAgent);
        $occurredAt = $incident->fresh()->occurred_at->toISOString();

        $this->actingAs($coordinator)
            ->patchJson(
                "/api/incidents/{$incident->id}/assign",
                [
                    'assigned_to_user_id' => $coordinator->id,
                    'expected_sync_version' => 1,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.assigned_to_user_id',
                $coordinator->id
            )
            ->assertJsonPath('data.occurred_at', $occurredAt)
            ->assertJsonPath('data.sync_version', 2);

        $this->actingAs($coordinator)
            ->patchJson(
                "/api/incidents/{$incident->id}/review",
                [
                    'status' => 'in_review',
                    'expected_sync_version' => 2,
                ]
            )
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review')
            ->assertJsonPath(
                'data.reviewed_by_user_id',
                $coordinator->id
            )
            ->assertJsonPath('data.occurred_at', $occurredAt)
            ->assertJsonPath('data.sync_version', 3);

        $this->actingAs($coordinator)
            ->patchJson(
                "/api/incidents/{$incident->id}/review",
                [
                    'status' => 'resolved',
                    'resolution_notes' => 'Resolved during API testing.',
                    'expected_sync_version' => 3,
                ]
            )
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath(
                'data.resolution_notes',
                'Resolved during API testing.'
            )
            ->assertJsonPath('data.occurred_at', $occurredAt)
            ->assertJsonPath('data.sync_version', 4);

        $this->assertSame(
            $occurredAt,
            $incident->fresh()->occurred_at->toISOString()
        );

        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'assigned_to_user_id' => $coordinator->id,
            'reviewed_by_user_id' => $coordinator->id,
            'status' => 'resolved',
        ]);

        $this->actingAs($coordinator)
            ->deleteJson("/api/incidents/{$incident->id}")
            ->assertForbidden();

        $this->actingAs($admin)
            ->deleteJson("/api/incidents/{$incident->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('incidents', [
            'id' => $incident->id,
        ]);
    }

    public function test_field_agent_can_manage_private_attachments_on_own_incident(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $fieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $incident = $this->createIncident($fieldAgent);
        $clientUuid = Str::uuid()->toString();

        $response = $this->actingAs($fieldAgent)
            ->withHeader('Accept', 'application/json')
            ->post(
                "/api/incidents/{$incident->id}/attachments",
                [
                    'file' => UploadedFile::fake()->create(
                        'evidence.pdf',
                        100,
                        'application/pdf'
                    ),
                    'client_uuid' => $clientUuid,
                    'captured_at' => now()
                        ->subMinute()
                        ->toISOString(),
                ]
            )
            ->assertCreated()
            ->assertJsonPath('data.client_uuid', $clientUuid)
            ->assertJsonPath(
                'data.original_name',
                'evidence.pdf'
            )
            ->assertJsonMissingPath('data.path')
            ->assertJsonMissingPath('data.checksum_sha256');

        $attachmentId = $response->json('data.id');

        $attachment = IncidentAttachment::query()
            ->findOrFail($attachmentId);

        Storage::disk('local')->assertExists(
            $attachment->path
        );

        $this->actingAs($fieldAgent)
            ->get(
                "/api/incident-attachments/{$attachment->id}/download"
            )
            ->assertOk()
            ->assertHeader(
                'Content-Type',
                $attachment->mime_type
            );

        $this->actingAs($fieldAgent)
            ->withHeader('Accept', 'application/json')
            ->post(
                "/api/incidents/{$incident->id}/attachments",
                [
                    'file' => UploadedFile::fake()->create(
                        'retry.pdf',
                        100,
                        'application/pdf'
                    ),
                    'client_uuid' => $clientUuid,
                ]
            )
            ->assertOk()
            ->assertJsonPath('data.id', $attachment->id);

        $this->assertDatabaseCount(
            'incident_attachments',
            1
        );

        $path = $attachment->path;

        $this->actingAs($fieldAgent)
            ->deleteJson(
                "/api/incident-attachments/{$attachment->id}"
            )
            ->assertNoContent();

        Storage::disk('local')->assertMissing($path);

        $this->assertDatabaseMissing('incident_attachments', [
            'id' => $attachment->id,
        ]);
    }

    public function test_field_agent_cannot_manage_another_reporters_attachments(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $fieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $otherAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $incident = $this->createIncident($otherAgent);

        $this->actingAs($fieldAgent)
            ->withHeader('Accept', 'application/json')
            ->post(
                "/api/incidents/{$incident->id}/attachments",
                [
                    'file' => UploadedFile::fake()->create(
                        'forbidden.pdf',
                        100,
                        'application/pdf'
                    ),
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'incident_attachments',
            0
        );
    }

    public function test_admin_cannot_access_another_tenants_incident(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $futureIncident = $this->createIncident($futureAdmin);

        $futureAttachment = $this->createAttachment(
            $futureAdmin,
            $futureIncident
        );

        $this->actingAs($cedraAdmin)
            ->getJson(
                "/api/incidents/{$futureIncident->id}"
            )
            ->assertNotFound();

        $this->actingAs($cedraAdmin)
            ->get(
                "/api/incident-attachments/{$futureAttachment->id}/download"
            )
            ->assertNotFound();
    }

    public function test_invalid_incident_filters_are_rejected(): void
    {
        $admin = $this->findUser('admin@cedra.test');

        $this->actingAs($admin)
            ->getJson(
                '/api/incidents?category=invalid&severity=urgent'
                .'&status=closed&per_page=500'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'category',
                'severity',
                'status',
                'per_page',
            ]);
    }

    private function validIncidentPayload(
        array $overrides = []
    ): array {
        return array_merge([
            'title' => 'Fictional API test incident',
            'description' => 'A fictional incident used only for automated testing.',
            'category' => 'safety',
            'severity' => 'high',
            'location_notes' => 'Fictional test location.',
            'occurred_at' => now()
                ->subMinutes(10)
                ->toISOString(),
            'client_updated_at' => now()
                ->subMinute()
                ->toISOString(),
        ], $overrides);
    }

    private function createIncident(
        User $reporter,
        array $overrides = []
    ): Incident {
        $this->actingAs($reporter);

        return Incident::create(array_merge([
            'reported_by_user_id' => $reporter->id,
            'title' => 'Fictional model incident',
            'description' => 'A fictional incident for API testing.',
            'category' => 'general',
            'severity' => 'medium',
            'status' => 'submitted',
            'occurred_at' => now()->subMinutes(15),
            'client_updated_at' => now()->subMinute(),
        ], $overrides));
    }

    private function createAttachment(
        User $uploader,
        Incident $incident
    ): IncidentAttachment {
        $this->actingAs($uploader);

        return IncidentAttachment::create([
            'incident_id' => $incident->id,
            'uploaded_by_user_id' => $uploader->id,
            'disk' => 'local',
            'path' => "incidents/testing/{$incident->id}.jpg",
            'original_name' => 'test-evidence.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'checksum_sha256' => str_repeat('a', 64),
        ]);
    }

    private function createTask(
        User $creator,
        User $assignee,
        Area $area
    ): CampaignTask {
        $this->actingAs($creator);

        return CampaignTask::create([
            'area_id' => $area->id,
            'created_by_user_id' => $creator->id,
            'assigned_to_user_id' => $assignee->id,
            'title' => 'Incident API field task',
            'description' => 'A fictional task for incident testing.',
            'type' => 'field_visit',
            'priority' => 'high',
            'status' => 'pending',
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

        $user->roles()->sync([$role->id]);

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

    /**
     * @return array{Area, PollingCenter, PollingStation}
     */
    private function findGeography(
        Tenant $tenant
    ): array {
        $area = Area::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $center = PollingCenter::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('area_id', $area->id)
            ->firstOrFail();

        $station = PollingStation::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('polling_center_id', $center->id)
            ->firstOrFail();

        return [$area, $center, $station];
    }
}
