<?php

namespace Tests\Feature;

use App\Models\PollingCenter;
use App\Models\PollingStation;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TurnoutSnapshot;
use App\Models\User;
use Database\Seeders\GeographySeeder;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TurnoutSnapshotApiTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_turnout_api(): void
    {
        $this->getJson('/api/turnout-snapshots')
            ->assertUnauthorized();

        $this->postJson('/api/turnout-snapshots', [])
            ->assertUnauthorized();

        $this->getJson(
            '/api/turnout-snapshots/series?polling_center_id=1'
        )->assertUnauthorized();
    }

    public function test_user_without_turnout_permissions_is_forbidden(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($user)
            ->getJson('/api/turnout-snapshots')
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson('/api/turnout-snapshots', [])
            ->assertForbidden();
    }

    public function test_admin_only_receives_and_filters_own_tenant_snapshots(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $matching = $this->createSnapshot(
            $cedraAdmin,
            [
                'turnout_count' => 210,
                'notes' => 'Morning checkpoint aggregate.',
            ]
        );

        $this->createSnapshot(
            $cedraAdmin,
            [
                'turnout_count' => 310,
                'notes' => 'Afternoon checkpoint aggregate.',
            ]
        );

        $futureSnapshot = $this->createSnapshot(
            $futureAdmin,
            [
                'turnout_count' => 410,
                'notes' => 'Morning checkpoint aggregate.',
            ]
        );

        $this->actingAs($cedraAdmin)
            ->getJson(
                '/api/turnout-snapshots?search=Morning&source=field'
            )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonMissing([
                'id' => $futureSnapshot->id,
            ]);
    }

    public function test_field_agent_can_submit_idempotent_offline_snapshot(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $fieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        [$center, $station] = $this->findGeography($tenant);

        $clientUuid = Str::uuid()->toString();

        $payload = $this->validPayload(
            $center,
            $station,
            [
                'client_uuid' => $clientUuid,
                'registered_voters' => 500,
                'turnout_count' => 125,
            ]
        );

        $this->actingAs($fieldAgent)
            ->postJson('/api/turnout-snapshots', $payload)
            ->assertCreated()
            ->assertJsonPath('data.client_uuid', $clientUuid)
            ->assertJsonPath(
                'data.reported_by_user_id',
                $fieldAgent->id
            )
            ->assertJsonPath('data.source', 'field')
            ->assertJsonPath('data.registered_voters', 500)
            ->assertJsonPath('data.turnout_count', 125)
            ->assertJsonPath('data.turnout_percentage', 25)
            ->assertJsonPath(
                'data.polling_center.id',
                $center->id
            )
            ->assertJsonPath(
                'data.polling_station.id',
                $station->id
            );

        $this->actingAs($fieldAgent)
            ->postJson('/api/turnout-snapshots', $payload)
            ->assertOk()
            ->assertJsonPath('data.client_uuid', $clientUuid);

        $this->assertDatabaseCount('turnout_snapshots', 1);

        $this->assertDatabaseHas('turnout_snapshots', [
            'tenant_id' => $tenant->id,
            'reported_by_user_id' => $fieldAgent->id,
            'polling_center_id' => $center->id,
            'polling_station_id' => $station->id,
            'client_uuid' => $clientUuid,
            'turnout_count' => 125,
            'source' => 'field',
        ]);
    }

    public function test_admin_submission_is_marked_as_admin_source(): void
    {
        $admin = $this->findUser('admin@cedra.test');

        [$center, $station] = $this->findGeography(
            $admin->tenant
        );

        $this->actingAs($admin)
            ->postJson(
                '/api/turnout-snapshots',
                $this->validPayload(
                    $center,
                    $station
                )
            )
            ->assertCreated()
            ->assertJsonPath('data.source', 'admin')
            ->assertJsonPath(
                'data.reported_by_user_id',
                $admin->id
            );
    }

    public function test_validation_protects_aggregate_and_internal_fields(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        [$center, $station] = $this->findGeography(
            $admin->tenant
        );

        [$futureCenter, $futureStation] = $this->findGeography(
            $futureAdmin->tenant
        );

        $this->actingAs($admin)
            ->postJson(
                '/api/turnout-snapshots',
                $this->validPayload(
                    $center,
                    $station,
                    [
                        'tenant_id' => $futureAdmin->tenant_id,
                        'reported_by_user_id' => $futureAdmin->id,
                        'reference_code' => 'FORGED-TURNOUT',
                        'source' => 'admin',
                        'received_at' => now()->toISOString(),
                    ]
                )
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'tenant_id',
                'reported_by_user_id',
                'reference_code',
                'source',
                'received_at',
            ]);

        $this->actingAs($admin)
            ->postJson(
                '/api/turnout-snapshots',
                $this->validPayload(
                    $futureCenter,
                    $futureStation
                )
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'polling_center_id',
                'polling_station_id',
            ]);

        $this->actingAs($admin)
            ->postJson(
                '/api/turnout-snapshots',
                $this->validPayload(
                    $center,
                    $station,
                    [
                        'registered_voters' => 100,
                        'turnout_count' => 101,
                    ]
                )
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'turnout_count',
            ]);

        $this->actingAs($admin)
            ->postJson(
                '/api/turnout-snapshots',
                $this->validPayload(
                    $center,
                    $station,
                    [
                        'client_uuid' => 'not-a-uuid',
                    ]
                )
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'client_uuid',
            ]);

        $this->assertDatabaseCount('turnout_snapshots', 0);
    }

    public function test_field_agent_only_accesses_own_snapshots(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $fieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $otherFieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $ownSnapshot = $this->createSnapshot($fieldAgent);
        $otherSnapshot = $this->createSnapshot($otherFieldAgent);

        $this->actingAs($fieldAgent)
            ->getJson('/api/turnout-snapshots')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownSnapshot->id)
            ->assertJsonMissing([
                'id' => $otherSnapshot->id,
            ]);

        $this->actingAs($fieldAgent)
            ->getJson(
                "/api/turnout-snapshots/{$ownSnapshot->id}"
            )
            ->assertOk()
            ->assertJsonPath('data.id', $ownSnapshot->id);

        $this->actingAs($fieldAgent)
            ->getJson(
                "/api/turnout-snapshots/{$otherSnapshot->id}"
            )
            ->assertForbidden();
    }

    public function test_series_returns_center_and_station_time_series(): void
    {
        $admin = $this->findUser('admin@cedra.test');

        [$center, $station] = $this->findGeography(
            $admin->tenant
        );

        $this->createSnapshot(
            $admin,
            [
                'polling_center_id' => $center->id,
                'polling_station_id' => null,
                'registered_voters' => 500,
                'turnout_count' => 100,
                'captured_at' => now()->subHours(3),
            ]
        );

        $this->createSnapshot(
            $admin,
            [
                'polling_center_id' => $center->id,
                'polling_station_id' => null,
                'registered_voters' => 500,
                'turnout_count' => 150,
                'captured_at' => now()->subHours(2),
            ]
        );

        $this->createSnapshot(
            $admin,
            [
                'polling_center_id' => $center->id,
                'polling_station_id' => null,
                'registered_voters' => 500,
                'turnout_count' => 190,
                'captured_at' => now()->subHour(),
            ]
        );

        $stationSnapshot = $this->createSnapshot(
            $admin,
            [
                'polling_center_id' => $center->id,
                'polling_station_id' => $station->id,
                'registered_voters' => 300,
                'turnout_count' => 90,
                'captured_at' => now()->subMinutes(30),
            ]
        );

        $this->actingAs($admin)
            ->getJson(
                "/api/turnout-snapshots/series?polling_center_id={$center->id}"
            )
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.turnout_count', 100)
            ->assertJsonPath('data.1.turnout_count', 150)
            ->assertJsonPath('data.2.turnout_count', 190)
            ->assertJsonPath('meta.points_count', 3)
            ->assertJsonPath('meta.latest_turnout_count', 190)
            ->assertJsonPath('meta.previous_turnout_count', 150)
            ->assertJsonPath('meta.change_since_previous', 40)
            ->assertJsonPath('meta.registered_voters', 500)
            ->assertJsonPath('meta.turnout_percentage', 38);

        $this->actingAs($admin)
            ->getJson(
                "/api/turnout-snapshots/series?polling_center_id={$center->id}&polling_station_id={$station->id}"
            )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $stationSnapshot->id
            )
            ->assertJsonPath('meta.points_count', 1)
            ->assertJsonPath(
                'meta.latest_turnout_count',
                90
            )
            ->assertJsonPath(
                'meta.change_since_previous',
                null
            )
            ->assertJsonPath(
                'meta.turnout_percentage',
                30
            );
    }

    public function test_admin_cannot_access_another_tenants_snapshot(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $futureSnapshot = $this->createSnapshot($futureAdmin);

        $this->actingAs($cedraAdmin)
            ->getJson(
                "/api/turnout-snapshots/{$futureSnapshot->id}"
            )
            ->assertNotFound();
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        [$futureCenter, $futureStation] = $this->findGeography(
            $futureAdmin->tenant
        );

        $this->actingAs($admin)
            ->getJson(
                '/api/turnout-snapshots?source=invalid&per_page=101'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'source',
                'per_page',
            ]);

        $this->actingAs($admin)
            ->getJson(
                "/api/turnout-snapshots/series?polling_center_id={$futureCenter->id}&polling_station_id={$futureStation->id}"
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'polling_center_id',
                'polling_station_id',
            ]);

        $this->actingAs($admin)
            ->getJson('/api/turnout-snapshots/series')
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'polling_center_id',
            ]);
    }

    private function validPayload(
        PollingCenter $center,
        PollingStation $station,
        array $overrides = []
    ): array {
        return array_merge([
            'polling_center_id' => $center->id,
            'polling_station_id' => $station->id,
            'client_uuid' => Str::uuid()->toString(),
            'registered_voters' => 500,
            'turnout_count' => 120,
            'notes' => 'Fictional aggregate turnout report.',
            'captured_at' => now()->subMinutes(5)->toISOString(),
        ], $overrides);
    }

    private function createSnapshot(
        User $reporter,
        array $overrides = []
    ): TurnoutSnapshot {
        $this->actingAs($reporter);

        [$center, $station] = $this->findGeography(
            $reporter->tenant
        );

        return TurnoutSnapshot::create(array_merge([
            'reported_by_user_id' => $reporter->id,
            'polling_center_id' => $center->id,
            'polling_station_id' => $station->id,
            'registered_voters' => 500,
            'turnout_count' => 120,
            'source' => 'field',
            'notes' => 'Fictional aggregate turnout snapshot.',
            'captured_at' => now()->subMinutes(5),
        ], $overrides));
    }

    /**
     * @return array{PollingCenter, PollingStation}
     */
    private function findGeography(Tenant $tenant): array
    {
        $station = PollingStation::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $center = PollingCenter::withoutGlobalScopes()
            ->whereKey($station->polling_center_id)
            ->firstOrFail();

        return [
            $center,
            $station,
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
