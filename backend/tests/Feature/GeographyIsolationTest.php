<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\District;
use App\Models\Governorate;
use App\Models\PollingCenter;
use App\Models\PollingStation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class GeographyIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_geography_hierarchy_and_relationships_work(): void
    {
        $tenant = $this->createTenant(
            'Cedra Campaign',
            'cedra-campaign'
        );

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($user);

        $governorate = Governorate::create([
            'name_en' => 'Beirut',
            'name_ar' => 'بيروت',
            'code' => 'BEY',
        ]);

        $district = District::create([
            'governorate_id' => $governorate->id,
            'name_en' => 'Beirut',
            'name_ar' => 'بيروت',
            'code' => 'BEY-D',
        ]);

        $area = Area::create([
            'district_id' => $district->id,
            'name_en' => 'Achrafieh',
            'name_ar' => 'الأشرفية',
            'code' => 'ACH',
            'type' => 'neighbourhood',
        ]);

        $pollingCenter = PollingCenter::create([
            'area_id' => $area->id,
            'name_en' => 'Achrafieh Public School',
            'name_ar' => 'مدرسة الأشرفية الرسمية',
            'code' => 'ACH-PC-01',
        ]);

        $pollingStation = PollingStation::create([
            'polling_center_id' => $pollingCenter->id,
            'station_number' => '1',
            'room' => 'Room 101',
            'registered_voters' => 850,
        ]);

        $this->assertSame($tenant->id, $governorate->tenant_id);
        $this->assertSame($tenant->id, $district->tenant_id);
        $this->assertSame($tenant->id, $area->tenant_id);
        $this->assertSame($tenant->id, $pollingCenter->tenant_id);
        $this->assertSame($tenant->id, $pollingStation->tenant_id);

        $this->assertTrue(
            $governorate->districts()->firstOrFail()->is($district)
        );
        $this->assertTrue($district->governorate->is($governorate));
        $this->assertTrue($district->areas()->firstOrFail()->is($area));
        $this->assertTrue($area->district->is($district));
        $this->assertTrue(
            $area->pollingCenters()->firstOrFail()->is($pollingCenter)
        );
        $this->assertTrue($pollingCenter->area->is($area));
        $this->assertTrue(
            $pollingCenter->pollingStations()
                ->firstOrFail()
                ->is($pollingStation)
        );
        $this->assertTrue(
            $pollingStation->pollingCenter->is($pollingCenter)
        );

        $this->assertSame(850, $pollingStation->registered_voters);
    }

    public function test_tenant_can_only_query_its_own_geography(): void
    {
        $tenantA = $this->createTenant(
            'Cedra Campaign',
            'cedra-campaign'
        );

        $tenantB = $this->createTenant(
            'Future Campaign',
            'future-campaign'
        );

        $userA = User::factory()->create([
            'tenant_id' => $tenantA->id,
        ]);

        $userB = User::factory()->create([
            'tenant_id' => $tenantB->id,
        ]);

        $this->actingAs($userA);

        $governorateA = Governorate::create([
            'name_en' => 'Beirut',
            'name_ar' => 'بيروت',
            'code' => 'BEY',
        ]);

        $this->actingAs($userB);

        $governorateB = Governorate::create([
            'name_en' => 'North',
            'name_ar' => 'الشمال',
            'code' => 'NORTH',
        ]);

        $this->actingAs($userA);

        $this->assertCount(1, Governorate::all());
        $this->assertTrue(Governorate::firstOrFail()->is($governorateA));
        $this->assertNull(Governorate::find($governorateB->id));

        $this->assertSame(
            2,
            Governorate::withoutGlobalScopes()->count()
        );
    }

    public function test_active_tenant_overrides_submitted_tenant_id(): void
    {
        $tenantA = $this->createTenant(
            'Cedra Campaign',
            'cedra-campaign'
        );

        $tenantB = $this->createTenant(
            'Future Campaign',
            'future-campaign'
        );

        $userA = User::factory()->create([
            'tenant_id' => $tenantA->id,
        ]);

        $this->actingAs($userA);

        $governorate = Governorate::create([
            'tenant_id' => $tenantB->id,
            'name_en' => 'Beirut',
            'name_ar' => 'بيروت',
            'code' => 'BEY',
        ]);

        $this->assertSame($tenantA->id, $governorate->tenant_id);

        $this->assertDatabaseHas('governorates', [
            'id' => $governorate->id,
            'tenant_id' => $tenantA->id,
        ]);
    }

    public function test_child_cannot_use_another_tenants_parent(): void
    {
        $tenantA = $this->createTenant(
            'Cedra Campaign',
            'cedra-campaign'
        );

        $tenantB = $this->createTenant(
            'Future Campaign',
            'future-campaign'
        );

        $userA = User::factory()->create([
            'tenant_id' => $tenantA->id,
        ]);

        $userB = User::factory()->create([
            'tenant_id' => $tenantB->id,
        ]);

        $this->actingAs($userB);

        $otherGovernorate = Governorate::create([
            'name_en' => 'North',
            'name_ar' => 'الشمال',
            'code' => 'NORTH',
        ]);

        $this->actingAs($userA);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'The parent geography record must belong to the same tenant.'
        );

        District::create([
            'governorate_id' => $otherGovernorate->id,
            'name_en' => 'Attempted District',
            'name_ar' => 'قضاء تجريبي',
            'code' => 'INVALID',
        ]);
    }

    private function createTenant(string $name, string $slug): Tenant
    {
        return Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
        ]);
    }
}
