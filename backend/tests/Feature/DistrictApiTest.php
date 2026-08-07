<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Governorate;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistrictApiTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_district_api(): void
    {
        $this->getJson('/api/districts')
            ->assertUnauthorized();
    }

    public function test_admin_only_receives_and_filters_own_districts(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');

        $beirut = $this->createGovernorate(
            $cedraTenant,
            'Beirut',
            'BEY'
        );

        $mountLebanon = $this->createGovernorate(
            $cedraTenant,
            'Mount Lebanon',
            'MLB'
        );

        $futureGovernorate = $this->createGovernorate(
            $futureTenant,
            'Future Governorate',
            'FUT'
        );

        $this->createDistrict(
            $cedraTenant,
            $beirut,
            'Beirut District',
            'LB-BA-BEIRUT'
        );

        $this->createDistrict(
            $cedraTenant,
            $mountLebanon,
            'Baabda',
            'BAA'
        );

        $this->createDistrict(
            $futureTenant,
            $futureGovernorate,
            'Future District',
            'FUT-D'
        );

        $admin = $this->cedraAdmin();

        $this->actingAs($admin)
            ->getJson('/api/districts')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing([
                'code' => 'FUT-D',
            ]);

        $this->getJson(
            "/api/districts?governorate_id={$beirut->id}"
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'code' => 'LB-BA-BEIRUT',
            ])
            ->assertJsonMissing([
                'code' => 'BAA',
            ]);
    }

    public function test_tenant_admin_can_create_update_and_delete_district(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $governorate = $this->createGovernorate(
            $tenant,
            'Beirut',
            'BEY'
        );

        $createResponse = $this->actingAs($this->cedraAdmin())
            ->postJson('/api/districts', [
                'governorate_id' => $governorate->id,
                'name_en' => 'Beirut District',
                'name_ar' => 'قضاء بيروت',
                'code' => 'LB-BA-BEIRUT',
            ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.code', 'LB-BA-BEIRUT')
            ->assertJsonPath(
                'data.governorate.id',
                $governorate->id
            );

        $districtId = $createResponse->json('data.id');

        $this->assertDatabaseHas('districts', [
            'id' => $districtId,
            'tenant_id' => $tenant->id,
            'governorate_id' => $governorate->id,
            'code' => 'LB-BA-BEIRUT',
        ]);

        $this->getJson("/api/districts/{$districtId}")
            ->assertOk()
            ->assertJsonPath('data.code', 'LB-BA-BEIRUT');

        $this->patchJson("/api/districts/{$districtId}", [
            'name_en' => 'Beirut District Updated',
        ])
            ->assertOk()
            ->assertJsonPath(
                'data.name_en',
                'Beirut District Updated'
            );

        $this->deleteJson("/api/districts/{$districtId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('districts', [
            'id' => $districtId,
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

        $futureGovernorate = $this->createGovernorate(
            $futureTenant,
            'Future Governorate',
            'FUT'
        );

        $this->actingAs($this->cedraAdmin())
            ->postJson('/api/districts', [
                'tenant_id' => $futureTenant->id,
                'governorate_id' => $cedraGovernorate->id,
                'name_en' => 'Invalid District',
                'name_ar' => 'قضاء غير صالح',
                'code' => 'INV-1',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'tenant_id',
            ]);

        $this->postJson('/api/districts', [
            'governorate_id' => $futureGovernorate->id,
            'name_en' => 'Cross Tenant District',
            'name_ar' => 'قضاء من مؤسسة أخرى',
            'code' => 'INV-2',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'governorate_id',
            ]);

        $this->assertDatabaseMissing('districts', [
            'code' => 'INV-1',
        ]);

        $this->assertDatabaseMissing('districts', [
            'code' => 'INV-2',
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

        $coordinator = $this->createUserWithRole(
            $tenant,
            'coordinator'
        );

        $this->actingAs($coordinator)
            ->postJson('/api/districts', [
                'governorate_id' => $governorate->id,
                'name_en' => 'Second District',
                'name_ar' => 'القضاء الثاني',
                'code' => 'SEC-D',
            ])
            ->assertCreated();

        $this->patchJson("/api/districts/{$district->id}", [
            'name_en' => 'Updated District',
        ])
            ->assertOk()
            ->assertJsonPath('data.name_en', 'Updated District');

        $this->deleteJson("/api/districts/{$district->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('districts', [
            'id' => $district->id,
        ]);
    }

    public function test_field_agent_can_view_but_cannot_modify_districts(): void
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

        $fieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $this->actingAs($fieldAgent)
            ->getJson('/api/districts')
            ->assertOk();

        $this->getJson("/api/districts/{$district->id}")
            ->assertOk();

        $this->postJson('/api/districts', [
            'governorate_id' => $governorate->id,
            'name_en' => 'Forbidden District',
            'name_ar' => 'قضاء ممنوع',
            'code' => 'FOR-D',
        ])
            ->assertForbidden();

        $this->patchJson("/api/districts/{$district->id}", [
            'name_en' => 'Forbidden Update',
        ])
            ->assertForbidden();

        $this->deleteJson("/api/districts/{$district->id}")
            ->assertForbidden();
    }

    public function test_district_code_must_be_unique_inside_tenant(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');

        $cedraGovernorate = $this->createGovernorate(
            $cedraTenant,
            'Cedra Governorate',
            'CED'
        );

        $futureGovernorate = $this->createGovernorate(
            $futureTenant,
            'Future Governorate',
            'FUT'
        );

        $this->createDistrict(
            $cedraTenant,
            $cedraGovernorate,
            'Cedra District',
            'DUP'
        );

        $this->createDistrict(
            $futureTenant,
            $futureGovernorate,
            'Future District',
            'DUP'
        );

        $this->actingAs($this->cedraAdmin())
            ->postJson('/api/districts', [
                'governorate_id' => $cedraGovernorate->id,
                'name_en' => 'Duplicate District',
                'name_ar' => 'قضاء مكرر',
                'code' => 'DUP',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'code',
            ]);
    }

    public function test_admin_cannot_access_another_tenants_district(): void
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

        $this->actingAs($this->cedraAdmin())
            ->getJson("/api/districts/{$futureDistrict->id}")
            ->assertNotFound();

        $this->patchJson(
            "/api/districts/{$futureDistrict->id}",
            ['name_en' => 'Forbidden Update']
        )
            ->assertNotFound();

        $this->deleteJson("/api/districts/{$futureDistrict->id}")
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
}
