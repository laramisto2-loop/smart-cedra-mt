<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\District;
use App\Models\Governorate;
use App\Models\PollingCenter;
use App\Models\PollingStation;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PollingStationApiTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_polling_station_api(): void
    {
        $this->getJson('/api/polling-stations')
            ->assertUnauthorized();
    }

    public function test_admin_only_receives_and_filters_own_polling_stations(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');

        $firstCenter = $this->createPollingCenterHierarchy(
            $cedraTenant,
            'CED1'
        );

        $secondCenter = $this->createPollingCenterHierarchy(
            $cedraTenant,
            'CED2'
        );

        $futureCenter = $this->createPollingCenterHierarchy(
            $futureTenant,
            'FUT'
        );

        $this->createPollingStation(
            $cedraTenant,
            $firstCenter,
            '1'
        );

        $this->createPollingStation(
            $cedraTenant,
            $secondCenter,
            '1'
        );

        $this->createPollingStation(
            $futureTenant,
            $futureCenter,
            '1'
        );

        $this->actingAs($this->cedraAdmin())
            ->getJson('/api/polling-stations')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson(
            '/api/polling-stations'
            ."?polling_center_id={$firstCenter->id}"
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.polling_center_id',
                $firstCenter->id
            );
    }

    public function test_tenant_admin_can_create_update_and_delete_polling_station(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $pollingCenter = $this->createPollingCenterHierarchy(
            $tenant,
            'CED'
        );

        $createResponse = $this->actingAs($this->cedraAdmin())
            ->postJson('/api/polling-stations', [
                'polling_center_id' => $pollingCenter->id,
                'station_number' => '1',
                'name_en' => 'Polling Station 1',
                'name_ar' => 'قلم الاقتراع 1',
                'room' => 'Room 101',
                'registered_voters' => 850,
            ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.station_number', '1')
            ->assertJsonPath('data.registered_voters', 850)
            ->assertJsonPath(
                'data.polling_center.id',
                $pollingCenter->id
            );

        $pollingStationId = $createResponse->json('data.id');

        $this->assertDatabaseHas('polling_stations', [
            'id' => $pollingStationId,
            'tenant_id' => $tenant->id,
            'polling_center_id' => $pollingCenter->id,
            'station_number' => '1',
            'registered_voters' => 850,
        ]);

        $this->getJson(
            "/api/polling-stations/{$pollingStationId}"
        )
            ->assertOk()
            ->assertJsonPath('data.station_number', '1');

        $this->patchJson(
            "/api/polling-stations/{$pollingStationId}",
            [
                'station_number' => '2',
                'room' => 'Room 102',
                'registered_voters' => 900,
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.station_number', '2')
            ->assertJsonPath('data.room', 'Room 102')
            ->assertJsonPath('data.registered_voters', 900);

        $this->deleteJson(
            "/api/polling-stations/{$pollingStationId}"
        )
            ->assertNoContent();

        $this->assertDatabaseMissing('polling_stations', [
            'id' => $pollingStationId,
        ]);
    }

    public function test_tenant_and_cross_tenant_parent_are_rejected(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');

        $cedraCenter = $this->createPollingCenterHierarchy(
            $cedraTenant,
            'CED'
        );

        $futureCenter = $this->createPollingCenterHierarchy(
            $futureTenant,
            'FUT'
        );

        $this->actingAs($this->cedraAdmin())
            ->postJson('/api/polling-stations', [
                'tenant_id' => $futureTenant->id,
                'polling_center_id' => $cedraCenter->id,
                'station_number' => '1',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'tenant_id',
            ]);

        $this->postJson('/api/polling-stations', [
            'polling_center_id' => $futureCenter->id,
            'station_number' => '2',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'polling_center_id',
            ]);

        $this->assertDatabaseMissing('polling_stations', [
            'station_number' => '1',
        ]);

        $this->assertDatabaseMissing('polling_stations', [
            'station_number' => '2',
        ]);
    }

    public function test_invalid_registered_voter_count_is_rejected(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $pollingCenter = $this->createPollingCenterHierarchy(
            $tenant,
            'CED'
        );

        $this->actingAs($this->cedraAdmin())
            ->postJson('/api/polling-stations', [
                'polling_center_id' => $pollingCenter->id,
                'station_number' => '1',
                'registered_voters' => -1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'registered_voters',
            ]);
    }

    public function test_coordinator_can_create_and_update_but_cannot_delete(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $pollingCenter = $this->createPollingCenterHierarchy(
            $tenant,
            'CED'
        );

        $pollingStation = $this->createPollingStation(
            $tenant,
            $pollingCenter,
            '1'
        );

        $coordinator = $this->createUserWithRole(
            $tenant,
            'coordinator'
        );

        $this->actingAs($coordinator)
            ->postJson('/api/polling-stations', [
                'polling_center_id' => $pollingCenter->id,
                'station_number' => '2',
                'room' => 'Room 102',
            ])
            ->assertCreated();

        $this->patchJson(
            "/api/polling-stations/{$pollingStation->id}",
            ['room' => 'Updated Room']
        )
            ->assertOk()
            ->assertJsonPath('data.room', 'Updated Room');

        $this->deleteJson(
            "/api/polling-stations/{$pollingStation->id}"
        )
            ->assertForbidden();

        $this->assertDatabaseHas('polling_stations', [
            'id' => $pollingStation->id,
        ]);
    }

    public function test_field_agent_can_view_but_cannot_modify_polling_stations(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $pollingCenter = $this->createPollingCenterHierarchy(
            $tenant,
            'CED'
        );

        $pollingStation = $this->createPollingStation(
            $tenant,
            $pollingCenter,
            '1'
        );

        $fieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $this->actingAs($fieldAgent)
            ->getJson('/api/polling-stations')
            ->assertOk();

        $this->getJson(
            "/api/polling-stations/{$pollingStation->id}"
        )
            ->assertOk();

        $this->postJson('/api/polling-stations', [
            'polling_center_id' => $pollingCenter->id,
            'station_number' => '2',
        ])
            ->assertForbidden();

        $this->patchJson(
            "/api/polling-stations/{$pollingStation->id}",
            ['room' => 'Forbidden Update']
        )
            ->assertForbidden();

        $this->deleteJson(
            "/api/polling-stations/{$pollingStation->id}"
        )
            ->assertForbidden();
    }

    public function test_station_number_is_unique_inside_polling_center(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $firstCenter = $this->createPollingCenterHierarchy(
            $tenant,
            'CED1'
        );

        $secondCenter = $this->createPollingCenterHierarchy(
            $tenant,
            'CED2'
        );

        $this->createPollingStation(
            $tenant,
            $firstCenter,
            '1'
        );

        $this->createPollingStation(
            $tenant,
            $secondCenter,
            '1'
        );

        $this->actingAs($this->cedraAdmin())
            ->postJson('/api/polling-stations', [
                'polling_center_id' => $firstCenter->id,
                'station_number' => '1',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'station_number',
            ]);
    }

    public function test_admin_cannot_access_another_tenants_polling_station(): void
    {
        $futureTenant = $this->findTenant('lebanon-future');

        $futureCenter = $this->createPollingCenterHierarchy(
            $futureTenant,
            'FUT'
        );

        $futureStation = $this->createPollingStation(
            $futureTenant,
            $futureCenter,
            '1'
        );

        $this->actingAs($this->cedraAdmin())
            ->getJson(
                "/api/polling-stations/{$futureStation->id}"
            )
            ->assertNotFound();

        $this->patchJson(
            "/api/polling-stations/{$futureStation->id}",
            ['room' => 'Forbidden Update']
        )
            ->assertNotFound();

        $this->deleteJson(
            "/api/polling-stations/{$futureStation->id}"
        )
            ->assertNotFound();
    }

    private function cedraAdmin(): User
    {
        return User::query()
            ->where('email', 'admin@cedra.test')
            ->firstOrFail();
    }

    private function findTenant(string $slug): Tenant
    {
        return Tenant::query()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    private function findRole(Tenant $tenant, string $slug): Role
    {
        return Role::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $slug)
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

    private function createPollingCenterHierarchy(
        Tenant $tenant,
        string $prefix
    ): PollingCenter {
        $governorate = Governorate::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name_en' => "{$prefix} Governorate",
            'name_ar' => "{$prefix} Governorate",
            'code' => "{$prefix}-G",
        ]);

        $district = District::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'governorate_id' => $governorate->id,
            'name_en' => "{$prefix} District",
            'name_ar' => "{$prefix} District",
            'code' => "{$prefix}-D",
        ]);

        $area = Area::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'district_id' => $district->id,
            'name_en' => "{$prefix} Area",
            'name_ar' => "{$prefix} Area",
            'code' => "{$prefix}-A",
            'type' => 'locality',
        ]);

        return PollingCenter::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'area_id' => $area->id,
            'name_en' => "{$prefix} Center",
            'name_ar' => "{$prefix} Center",
            'code' => "{$prefix}-PC",
        ]);
    }

    private function createPollingStation(
        Tenant $tenant,
        PollingCenter $pollingCenter,
        string $stationNumber
    ): PollingStation {
        return PollingStation::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'polling_center_id' => $pollingCenter->id,
            'station_number' => $stationNumber,
            'name_en' => "Polling Station {$stationNumber}",
            'name_ar' => "Polling Station {$stationNumber}",
            'room' => "Room {$stationNumber}",
            'registered_voters' => 800,
        ]);
    }
}
