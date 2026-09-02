<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\District;
use App\Models\Governorate;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AreaApiTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_area_api(): void
    {
        $this->getJson('/api/areas')
            ->assertUnauthorized();
    }

    public function test_admin_only_receives_and_filters_own_areas(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');

        $cedraGovernorate = $this->createGovernorate(
            $cedraTenant,
            'Beirut',
            'BEY'
        );

        $firstDistrict = $this->createDistrict(
            $cedraTenant,
            $cedraGovernorate,
            'Beirut District',
            'LB-BA-BEIRUT'
        );

        $secondDistrict = $this->createDistrict(
            $cedraTenant,
            $cedraGovernorate,
            'Second District',
            'SEC-D'
        );

        $futureGovernorate = $this->createGovernorate(
            $futureTenant,
            'Future Governorate',
            'FUT'
        );

        $futureDistrict = $this->createDistrict(
            $futureTenant,
            $futureGovernorate,
            'Future District',
            'FUT-D'
        );

        $this->createArea(
            $cedraTenant,
            $firstDistrict,
            'Achrafieh',
            'LB-BA-BEIRUT-ACHRAFIEH'
        );

        $this->createArea(
            $cedraTenant,
            $secondDistrict,
            'Second Area',
            'SEC-A'
        );

        $this->createArea(
            $futureTenant,
            $futureDistrict,
            'Future Area',
            'FUT-A'
        );

        $this->actingAs($this->cedraAdmin())
            ->getJson('/api/areas')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing([
                'code' => 'FUT-A',
            ]);

        $this->getJson(
            "/api/areas?district_id={$firstDistrict->id}"
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'code' => 'LB-BA-BEIRUT-ACHRAFIEH',
            ])
            ->assertJsonMissing([
                'code' => 'SEC-A',
            ]);
    }

    public function test_admin_can_search_areas_by_name_or_code(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $governorate = $this->createGovernorate(
            $tenant,
            'Search Governorate',
            'SRCH-G'
        );
        $district = $this->createDistrict(
            $tenant,
            $governorate,
            'Search District',
            'SRCH-D'
        );

        $this->createArea($tenant, $district, 'Searchable Area', 'SRCH-A');
        $this->createArea($tenant, $district, 'Unrelated Area', 'OTHER-A');

        $this->actingAs($this->cedraAdmin())
            ->getJson('/api/areas?search=SRCH-A')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name_en', 'Searchable Area');
    }

    public function test_tenant_admin_can_create_update_and_delete_area(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $governorate = $this->createGovernorate(
            $tenant,
            'Beirut',
            'BEY'
        );

        $district = $this->createDistrict(
            $tenant,
            $governorate,
            'Beirut District',
            'LB-BA-BEIRUT'
        );

        $createResponse = $this->actingAs($this->cedraAdmin())
            ->postJson('/api/areas', [
                'district_id' => $district->id,
                'name_en' => 'Achrafieh',
                'name_ar' => 'الأشرفية',
                'code' => 'LB-BA-BEIRUT-ACHRAFIEH',
                'type' => 'neighbourhood',
                'latitude' => 33.8938,
                'longitude' => 35.5018,
            ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.code', 'LB-BA-BEIRUT-ACHRAFIEH')
            ->assertJsonPath('data.type', 'neighbourhood')
            ->assertJsonPath('data.district.id', $district->id);

        $areaId = $createResponse->json('data.id');

        $this->assertDatabaseHas('areas', [
            'id' => $areaId,
            'tenant_id' => $tenant->id,
            'district_id' => $district->id,
            'code' => 'LB-BA-BEIRUT-ACHRAFIEH',
            'type' => 'neighbourhood',
        ]);

        $this->getJson("/api/areas/{$areaId}")
            ->assertOk()
            ->assertJsonPath('data.code', 'LB-BA-BEIRUT-ACHRAFIEH');

        $this->patchJson("/api/areas/{$areaId}", [
            'name_en' => 'Achrafieh Updated',
            'type' => 'locality',
        ])
            ->assertOk()
            ->assertJsonPath(
                'data.name_en',
                'Achrafieh Updated'
            )
            ->assertJsonPath('data.type', 'locality');

        $this->deleteJson("/api/areas/{$areaId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('areas', [
            'id' => $areaId,
        ]);
    }

    public function test_tenant_and_cross_tenant_parent_are_rejected(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');

        $cedraGovernorate = $this->createGovernorate(
            $cedraTenant,
            'Beirut',
            'BEY'
        );

        $cedraDistrict = $this->createDistrict(
            $cedraTenant,
            $cedraGovernorate,
            'Beirut District',
            'LB-BA-BEIRUT'
        );

        $futureGovernorate = $this->createGovernorate(
            $futureTenant,
            'Future Governorate',
            'FUT'
        );

        $futureDistrict = $this->createDistrict(
            $futureTenant,
            $futureGovernorate,
            'Future District',
            'FUT-D'
        );

        $this->actingAs($this->cedraAdmin())
            ->postJson('/api/areas', [
                'tenant_id' => $futureTenant->id,
                'district_id' => $cedraDistrict->id,
                'name_en' => 'Invalid Area',
                'name_ar' => 'منطقة غير صالحة',
                'code' => 'INV-1',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'tenant_id',
            ]);

        $this->postJson('/api/areas', [
            'district_id' => $futureDistrict->id,
            'name_en' => 'Cross Tenant Area',
            'name_ar' => 'منطقة من مؤسسة أخرى',
            'code' => 'INV-2',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'district_id',
            ]);

        $this->assertDatabaseMissing('areas', [
            'code' => 'INV-1',
        ]);

        $this->assertDatabaseMissing('areas', [
            'code' => 'INV-2',
        ]);
    }

    public function test_invalid_type_and_coordinates_are_rejected(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $governorate = $this->createGovernorate(
            $tenant,
            'Beirut',
            'BEY'
        );

        $district = $this->createDistrict(
            $tenant,
            $governorate,
            'Beirut District',
            'LB-BA-BEIRUT'
        );

        $this->actingAs($this->cedraAdmin())
            ->postJson('/api/areas', [
                'district_id' => $district->id,
                'name_en' => 'Invalid Area',
                'name_ar' => 'منطقة غير صالحة',
                'code' => 'INV',
                'type' => 'planet',
                'latitude' => 91,
                'longitude' => -181,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'type',
                'latitude',
                'longitude',
            ]);
    }

    public function test_coordinator_can_create_and_update_but_cannot_delete(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $governorate = $this->createGovernorate(
            $tenant,
            'Beirut',
            'BEY'
        );

        $district = $this->createDistrict(
            $tenant,
            $governorate,
            'Beirut District',
            'LB-BA-BEIRUT'
        );

        $area = $this->createArea(
            $tenant,
            $district,
            'Achrafieh',
            'LB-BA-BEIRUT-ACHRAFIEH'
        );

        $coordinator = $this->createUserWithRole(
            $tenant,
            'coordinator'
        );

        $this->actingAs($coordinator)
            ->postJson('/api/areas', [
                'district_id' => $district->id,
                'name_en' => 'Second Area',
                'name_ar' => 'المنطقة الثانية',
                'code' => 'SEC-A',
                'type' => 'village',
            ])
            ->assertCreated();

        $this->patchJson("/api/areas/{$area->id}", [
            'name_en' => 'Updated Area',
        ])
            ->assertOk()
            ->assertJsonPath('data.name_en', 'Updated Area');

        $this->deleteJson("/api/areas/{$area->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('areas', [
            'id' => $area->id,
        ]);
    }

    public function test_field_agent_can_view_but_cannot_modify_areas(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $governorate = $this->createGovernorate(
            $tenant,
            'Beirut',
            'BEY'
        );

        $district = $this->createDistrict(
            $tenant,
            $governorate,
            'Beirut District',
            'LB-BA-BEIRUT'
        );

        $area = $this->createArea(
            $tenant,
            $district,
            'Achrafieh',
            'LB-BA-BEIRUT-ACHRAFIEH'
        );

        $fieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $this->actingAs($fieldAgent)
            ->getJson('/api/areas')
            ->assertOk();

        $this->getJson("/api/areas/{$area->id}")
            ->assertOk();

        $this->postJson('/api/areas', [
            'district_id' => $district->id,
            'name_en' => 'Forbidden Area',
            'name_ar' => 'منطقة ممنوعة',
            'code' => 'FOR-A',
        ])
            ->assertForbidden();

        $this->patchJson("/api/areas/{$area->id}", [
            'name_en' => 'Forbidden Update',
        ])
            ->assertForbidden();

        $this->deleteJson("/api/areas/{$area->id}")
            ->assertForbidden();
    }

    public function test_area_code_must_be_unique_inside_tenant(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');

        $cedraGovernorate = $this->createGovernorate(
            $cedraTenant,
            'Cedra Governorate',
            'CED'
        );

        $cedraDistrict = $this->createDistrict(
            $cedraTenant,
            $cedraGovernorate,
            'Cedra District',
            'CED-D'
        );

        $futureGovernorate = $this->createGovernorate(
            $futureTenant,
            'Future Governorate',
            'FUT'
        );

        $futureDistrict = $this->createDistrict(
            $futureTenant,
            $futureGovernorate,
            'Future District',
            'FUT-D'
        );

        $this->createArea(
            $cedraTenant,
            $cedraDistrict,
            'Cedra Area',
            'DUP'
        );

        $this->createArea(
            $futureTenant,
            $futureDistrict,
            'Future Area',
            'DUP'
        );

        $this->actingAs($this->cedraAdmin())
            ->postJson('/api/areas', [
                'district_id' => $cedraDistrict->id,
                'name_en' => 'Duplicate Area',
                'name_ar' => 'منطقة مكررة',
                'code' => 'DUP',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'code',
            ]);
    }

    public function test_admin_cannot_access_another_tenants_area(): void
    {
        $futureTenant = $this->findTenant('lebanon-future');

        $futureGovernorate = $this->createGovernorate(
            $futureTenant,
            'Future Governorate',
            'FUT'
        );

        $futureDistrict = $this->createDistrict(
            $futureTenant,
            $futureGovernorate,
            'Future District',
            'FUT-D'
        );

        $futureArea = $this->createArea(
            $futureTenant,
            $futureDistrict,
            'Future Area',
            'FUT-A'
        );

        $this->actingAs($this->cedraAdmin())
            ->getJson("/api/areas/{$futureArea->id}")
            ->assertNotFound();

        $this->patchJson(
            "/api/areas/{$futureArea->id}",
            ['name_en' => 'Forbidden Update']
        )
            ->assertNotFound();

        $this->deleteJson("/api/areas/{$futureArea->id}")
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

    private function createGovernorate(
        Tenant $tenant,
        string $name,
        string $code
    ): Governorate {
        return Governorate::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name_en' => $name,
            'name_ar' => $name,
            'code' => $code,
        ]);
    }

    private function createDistrict(
        Tenant $tenant,
        Governorate $governorate,
        string $name,
        string $code
    ): District {
        return District::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'governorate_id' => $governorate->id,
            'name_en' => $name,
            'name_ar' => $name,
            'code' => $code,
        ]);
    }

    private function createArea(
        Tenant $tenant,
        District $district,
        string $name,
        string $code
    ): Area {
        return Area::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'district_id' => $district->id,
            'name_en' => $name,
            'name_ar' => $name,
            'code' => $code,
            'type' => 'locality',
        ]);
    }
}
