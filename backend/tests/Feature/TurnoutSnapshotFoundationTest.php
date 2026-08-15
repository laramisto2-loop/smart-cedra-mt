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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class TurnoutSnapshotFoundationTest extends TestCase
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

    public function test_snapshot_relationships_and_aggregate_identity_work(): void
    {
        $admin = $this->findUser('admin@cedra.test');

        $fieldAgent = $this->createUserWithRole(
            $admin->tenant,
            'field_agent'
        );

        [$pollingCenter, $pollingStation] =
            $this->findGeography($fieldAgent);

        $clientUuid = Str::uuid()->toString();

        $snapshot = $this->createSnapshot(
            $fieldAgent,
            [
                'client_uuid' => $clientUuid,
                'polling_center_id' => $pollingCenter->id,
                'polling_station_id' => $pollingStation->id,
            ]
        );

        $snapshot->refresh();

        $this->assertSame(
            $fieldAgent->tenant_id,
            $snapshot->tenant_id
        );

        $this->assertSame($clientUuid, $snapshot->client_uuid);

        $this->assertMatchesRegularExpression(
            '/^TUR-[A-F0-9]{12}$/',
            $snapshot->reference_code
        );

        $this->assertSame('field', $snapshot->source);
        $this->assertSame(500, $snapshot->registered_voters);
        $this->assertSame(120, $snapshot->turnout_count);
        $this->assertNotNull($snapshot->captured_at);
        $this->assertNotNull($snapshot->received_at);

        $this->assertTrue(
            $snapshot->tenant->is($fieldAgent->tenant)
        );

        $this->assertTrue(
            $snapshot->reporter->is($fieldAgent)
        );

        $this->assertTrue(
            $snapshot->pollingCenter->is($pollingCenter)
        );

        $this->assertTrue(
            $snapshot->pollingStation->is($pollingStation)
        );

        $this->assertTrue(
            $fieldAgent->tenant->turnoutSnapshots()
                ->firstOrFail()
                ->is($snapshot)
        );

        $this->assertTrue(
            $fieldAgent->reportedTurnoutSnapshots()
                ->firstOrFail()
                ->is($snapshot)
        );

        $this->assertTrue(
            $pollingCenter->turnoutSnapshots()
                ->firstOrFail()
                ->is($snapshot)
        );

        $this->assertTrue(
            $pollingStation->turnoutSnapshots()
                ->firstOrFail()
                ->is($snapshot)
        );
    }

    public function test_tenant_only_queries_its_own_snapshots(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $cedraSnapshot = $this->createSnapshot($cedraAdmin);
        $futureSnapshot = $this->createSnapshot($futureAdmin);

        $this->actingAs($cedraAdmin);

        $this->assertCount(1, TurnoutSnapshot::all());

        $this->assertTrue(
            TurnoutSnapshot::firstOrFail()->is($cedraSnapshot)
        );

        $this->assertNull(
            TurnoutSnapshot::find($futureSnapshot->id)
        );

        $this->assertSame(
            2,
            TurnoutSnapshot::withoutGlobalScopes()->count()
        );
    }

    public function test_active_tenant_overrides_submitted_tenant_id(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $snapshot = $this->createSnapshot(
            $cedraAdmin,
            ['tenant_id' => $futureAdmin->tenant_id]
        );

        $this->assertSame(
            $cedraAdmin->tenant_id,
            $snapshot->tenant_id
        );
    }

    public function test_snapshot_rejects_cross_tenant_relationships(): void
    {
        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        [$futureCenter, $futureStation] =
            $this->findGeography($futureAdmin);

        $this->assertSnapshotCreationFails(
            $cedraAdmin,
            ['reported_by_user_id' => $futureAdmin->id],
            'The turnout snapshot reporter must belong to the same tenant.'
        );

        $this->assertSnapshotCreationFails(
            $cedraAdmin,
            ['polling_center_id' => $futureCenter->id],
            'The turnout snapshot polling center must belong to the same tenant.'
        );

        $this->assertSnapshotCreationFails(
            $cedraAdmin,
            ['polling_station_id' => $futureStation->id],
            'The turnout snapshot polling station must belong to the same tenant.'
        );
    }

    public function test_snapshot_rejects_inconsistent_geography(): void
    {
        $admin = $this->findUser('admin@cedra.test');

        [$pollingCenter, $pollingStation] =
            $this->findGeography($admin);

        $this->actingAs($admin);

        $otherCenter = PollingCenter::create([
            'area_id' => $pollingCenter->area_id,
            'name_en' => 'Other Turnout Center',
            'name_ar' => 'Other Turnout Center',
            'code' => 'TURNOUT-OTHER-CENTER',
        ]);

        $this->assertSnapshotCreationFails(
            $admin,
            [
                'polling_center_id' => $otherCenter->id,
                'polling_station_id' => $pollingStation->id,
            ],
            'The turnout snapshot polling station must belong to the selected polling center.'
        );
    }

    public function test_snapshot_validates_aggregate_counts(): void
    {
        $admin = $this->findUser('admin@cedra.test');

        $this->assertSnapshotCreationFails(
            $admin,
            ['polling_center_id' => null],
            'A turnout snapshot must belong to a polling center.'
        );

        $this->assertSnapshotCreationFails(
            $admin,
            ['turnout_count' => -1],
            'The turnout count cannot be negative.'
        );

        $this->assertSnapshotCreationFails(
            $admin,
            ['registered_voters' => -1],
            'The registered voter count cannot be negative.'
        );

        $this->assertSnapshotCreationFails(
            $admin,
            [
                'registered_voters' => 100,
                'turnout_count' => 101,
            ],
            'The turnout count cannot exceed the registered voter count.'
        );
    }

    public function test_snapshots_are_immutable_and_offline_uuid_is_unique(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $clientUuid = Str::uuid()->toString();

        $snapshot = $this->createSnapshot(
            $admin,
            ['client_uuid' => $clientUuid]
        );

        try {
            $snapshot->update(['turnout_count' => 121]);

            $this->fail(
                'A historical turnout snapshot should not be updated.'
            );
        } catch (LogicException $exception) {
            $this->assertSame(
                'Turnout snapshots are immutable. Record a new snapshot to correct a count.',
                $exception->getMessage()
            );
        }

        $snapshot->refresh();

        try {
            $snapshot->delete();

            $this->fail(
                'A historical turnout snapshot should not be deleted.'
            );
        } catch (LogicException $exception) {
            $this->assertSame(
                'Turnout snapshots cannot be deleted because they form part of the reporting history.',
                $exception->getMessage()
            );
        }

        try {
            $this->createSnapshot(
                $admin,
                ['client_uuid' => $clientUuid]
            );

            $this->fail(
                'A duplicate offline UUID should not create another snapshot.'
            );
        } catch (QueryException) {
            $this->assertSame(
                1,
                TurnoutSnapshot::withoutGlobalScopes()->count()
            );
        }
    }

    public function test_policy_enforces_roles_ownership_and_tenants(): void
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

        $ownSnapshot = $this->createSnapshot($fieldAgent);
        $otherSnapshot = $this->createSnapshot($otherFieldAgent);
        $otherTenantSnapshot = $this->createSnapshot($futureAdmin);
        $this->actingAs($admin);

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'viewAny',
                TurnoutSnapshot::class
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'create',
                TurnoutSnapshot::class
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'view',
                $otherSnapshot
            )
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'update',
                $ownSnapshot
            )
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'delete',
                $ownSnapshot
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'viewAny',
                TurnoutSnapshot::class
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'create',
                TurnoutSnapshot::class
            )
        );

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'view',
                $otherSnapshot
            )
        );

        $this->assertTrue(
            Gate::forUser($fieldAgent)->allows(
                'viewAny',
                TurnoutSnapshot::class
            )
        );

        $this->assertTrue(
            Gate::forUser($fieldAgent)->allows(
                'create',
                TurnoutSnapshot::class
            )
        );

        $this->assertTrue(
            Gate::forUser($fieldAgent)->allows(
                'view',
                $ownSnapshot
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'view',
                $otherSnapshot
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'update',
                $ownSnapshot
            )
        );

        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'delete',
                $ownSnapshot
            )
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'view',
                $otherTenantSnapshot
            )
        );
    }

    private function createSnapshot(
        User $reporter,
        array $overrides = []
    ): TurnoutSnapshot {
        $this->actingAs($reporter);

        [$pollingCenter, $pollingStation] =
            $this->findGeography($reporter);

        return TurnoutSnapshot::create(array_merge([
            'reported_by_user_id' => $reporter->id,
            'polling_center_id' => $pollingCenter->id,
            'polling_station_id' => $pollingStation->id,
            'registered_voters' => 500,
            'turnout_count' => 120,
            'source' => 'field',
            'notes' => 'Fictional aggregate turnout snapshot.',
            'captured_at' => now()->subMinutes(5),
        ], $overrides));
    }

    private function assertSnapshotCreationFails(
        User $actor,
        array $overrides,
        string $expectedMessage
    ): void {
        try {
            $this->createSnapshot($actor, $overrides);

            $this->fail(
                'The invalid turnout snapshot should have been rejected.'
            );
        } catch (LogicException $exception) {
            $this->assertSame(
                $expectedMessage,
                $exception->getMessage()
            );
        }
    }

    /**
     * @return array{PollingCenter, PollingStation}
     */
    private function findGeography(User $user): array
    {
        $pollingStation = PollingStation::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->firstOrFail();

        $pollingCenter = PollingCenter::withoutGlobalScopes()
            ->whereKey($pollingStation->polling_center_id)
            ->firstOrFail();

        return [
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
