<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\District;
use App\Models\Governorate;
use App\Models\PollingCenter;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PollingCenterApiTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_polling_center_api(): void
    {
        $this->getJson('/api/polling-centers')
            ->assertUnauthorized();
    }

    public function test_admin_only_receives_and_filters_own_polling_centers(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');

        $firstArea = $this->createAreaHierarchy(
            $cedraTenant,
            'CED1'
        );

        $secondArea = $this->createAreaHierarchy(
            $cedraTenant,
            'CED2'
        );

        $futureArea = $this->createAreaHierarchy(
            $futureTenant,
            'FUT'
        );

        $this->createPollingCenter(
            $cedraTenant,
            $firstArea,
            'First Center',
            'CED1-PC'
        );

        $this->createPollingCenter(
            $cedraTenant,
            $secondArea,
            'Second Center',
            'CED2-PC'
        );

        $this->createPollingCenter(
            $futureTenant,
            $futureArea,
            'Future Center',
            'FUT-PC'
        );

        $this->actingAs($this->cedraAdmin())
            ->getJson('/api/polling-centers')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing([
                'code' => 'FUT-PC',
            ]);

        $this->getJson(
            "/api/polling-centers?area_id={$firstArea->id}"
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'code' => 'CED1-PC',
            ])
            ->assertJsonMissing([
                'code' => 'CED2-PC',
            ]);
    }

    public function test_admin_can_search_polling_centers_by_name_or_code(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $area = $this->createAreaHierarchy($tenant, 'SRCH');

        $this->createPollingCenter(
            $tenant,
            $area,
            'Searchable Center',
            'SRCH-PC'
        );
        $this->createPollingCenter(
            $tenant,
            $area,
            'Unrelated Center',
            'OTHER-PC'
        );

        $this->actingAs($this->cedraAdmin())
            ->getJson('/api/polling-centers?search=SRCH-PC')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name_en', 'Searchable Center');
    }

    public function test_tenant_admin_can_create_update_and_delete_polling_center(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $area = $this->createAreaHierarchy($tenant, 'CED');

        $createResponse = $this->actingAs($this->cedraAdmin())
            ->postJson('/api/polling-centers', [
                'area_id' => $area->id,
                'name_en' => 'Achrafieh Public School',
                'name_ar' => 'مدرسة الأشرفية الرسمية',
                'code' => 'ACH-PC',
                'address_en' => 'Achrafieh, Beirut',
                'address_ar' => 'الأشرفية، بيروت',
                'latitude' => 33.8938,
                'longitude' => 35.5018,
            ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.code', 'ACH-PC')
            ->assertJsonPath('data.area.id', $area->id);

        $pollingCenterId = $createResponse->json('data.id');

        $this->assertDatabaseHas('polling_centers', [
            'id' => $pollingCenterId,
            'tenant_id' => $tenant->id,
            'area_id' => $area->id,
            'code' => 'ACH-PC',
        ]);

        $this->getJson(
            "/api/polling-centers/{$pollingCenterId}"
        )
            ->assertOk()
            ->assertJsonPath('data.code', 'ACH-PC');

        $this->patchJson(
            "/api/polling-centers/{$pollingCenterId}",
            [
                'name_en' => 'Updated Public School',
                'address_en' => 'Updated Address',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.name_en',
                'Updated Public School'
            )
            ->assertJsonPath(
                'data.address_en',
                'Updated Address'
            );

        $this->deleteJson(
            "/api/polling-centers/{$pollingCenterId}"
        )
            ->assertNoContent();

        $this->assertDatabaseMissing('polling_centers', [
            'id' => $pollingCenterId,
        ]);
    }

    public function test_tenant_and_cross_tenant_parent_are_rejected(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');

        $cedraArea = $this->createAreaHierarchy(
            $cedraTenant,
            'CED'
        );

        $futureArea = $this->createAreaHierarchy(
            $futureTenant,
            'FUT'
        );

        $this->actingAs($this->cedraAdmin())
            ->postJson('/api/polling-centers', [
                'tenant_id' => $futureTenant->id,
                'area_id' => $cedraArea->id,
                'name_en' => 'Invalid Center',
                'name_ar' => 'مركز غير صالح',
                'code' => 'INV-1',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'tenant_id',
            ]);

        $this->postJson('/api/polling-centers', [
            'area_id' => $futureArea->id,
            'name_en' => 'Cross Tenant Center',
            'name_ar' => 'مركز من مؤسسة أخرى',
            'code' => 'INV-2',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'area_id',
            ]);

        $this->assertDatabaseMissing('polling_centers', [
            'code' => 'INV-1',
        ]);

        $this->assertDatabaseMissing('polling_centers', [
            'code' => 'INV-2',
        ]);
    }

    public function test_invalid_coordinates_are_rejected(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $area = $this->createAreaHierarchy($tenant, 'CED');

        $this->actingAs($this->cedraAdmin())
            ->postJson('/api/polling-centers', [
                'area_id' => $area->id,
                'name_en' => 'Invalid Center',
                'name_ar' => 'مركز غير صالح',
                'code' => 'INV',
                'latitude' => 91,
                'longitude' => -181,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'latitude',
                'longitude',
            ]);
    }

    public function test_coordinator_can_create_and_update_but_cannot_delete(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $area = $this->createAreaHierarchy($tenant, 'CED');

        $pollingCenter = $this->createPollingCenter(
            $tenant,
            $area,
            'First Center',
            'CED-PC'
        );

        $coordinator = $this->createUserWithRole(
            $tenant,
            'coordinator'
        );

        $this->actingAs($coordinator)
            ->postJson('/api/polling-centers', [
                'area_id' => $area->id,
                'name_en' => 'Second Center',
                'name_ar' => 'المركز الثاني',
                'code' => 'SEC-PC',
            ])
            ->assertCreated();

        $this->patchJson(
            "/api/polling-centers/{$pollingCenter->id}",
            ['name_en' => 'Updated Center']
        )
            ->assertOk()
            ->assertJsonPath('data.name_en', 'Updated Center');

        $this->deleteJson(
            "/api/polling-centers/{$pollingCenter->id}"
        )
            ->assertForbidden();

        $this->assertDatabaseHas('polling_centers', [
            'id' => $pollingCenter->id,
        ]);
    }

    public function test_field_agent_can_view_but_cannot_modify_polling_centers(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $area = $this->createAreaHierarchy($tenant, 'CED');

        $pollingCenter = $this->createPollingCenter(
            $tenant,
            $area,
            'First Center',
            'CED-PC'
        );

        $fieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $this->actingAs($fieldAgent)
            ->getJson('/api/polling-centers')
            ->assertOk();

        $this->getJson(
            "/api/polling-centers/{$pollingCenter->id}"
        )
            ->assertOk();

        $this->postJson('/api/polling-centers', [
            'area_id' => $area->id,
            'name_en' => 'Forbidden Center',
            'name_ar' => 'مركز ممنوع',
            'code' => 'FOR-PC',
        ])
            ->assertForbidden();

        $this->patchJson(
            "/api/polling-centers/{$pollingCenter->id}",
            ['name_en' => 'Forbidden Update']
        )
            ->assertForbidden();

        $this->deleteJson(
            "/api/polling-centers/{$pollingCenter->id}"
        )
            ->assertForbidden();
    }

    public function test_polling_center_code_must_be_unique_inside_tenant(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');

        $cedraArea = $this->createAreaHierarchy(
            $cedraTenant,
            'CED'
        );

        $futureArea = $this->createAreaHierarchy(
            $futureTenant,
            'FUT'
        );

        $this->createPollingCenter(
            $cedraTenant,
            $cedraArea,
            'Cedra Center',
            'DUP'
        );

        $this->createPollingCenter(
            $futureTenant,
            $futureArea,
            'Future Center',
            'DUP'
        );

        $this->actingAs($this->cedraAdmin())
            ->postJson('/api/polling-centers', [
                'area_id' => $cedraArea->id,
                'name_en' => 'Duplicate Center',
                'name_ar' => 'مركز مكرر',
                'code' => 'DUP',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'code',
            ]);
    }

    public function test_admin_cannot_access_another_tenants_polling_center(): void
    {
        $futureTenant = $this->findTenant('lebanon-future');

        $futureArea = $this->createAreaHierarchy(
            $futureTenant,
            'FUT'
        );

        $futureCenter = $this->createPollingCenter(
            $futureTenant,
            $futureArea,
            'Future Center',
            'FUT-PC'
        );

        $this->actingAs($this->cedraAdmin())
            ->getJson(
                "/api/polling-centers/{$futureCenter->id}"
            )
            ->assertNotFound();

        $this->patchJson(
            "/api/polling-centers/{$futureCenter->id}",
            ['name_en' => 'Forbidden Update']
        )
            ->assertNotFound();

        $this->deleteJson(
            "/api/polling-centers/{$futureCenter->id}"
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

    private function createAreaHierarchy(
        Tenant $tenant,
        string $prefix
    ): Area {
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

        return Area::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'district_id' => $district->id,
            'name_en' => "{$prefix} Area",
            'name_ar' => "{$prefix} Area",
            'code' => "{$prefix}-A",
            'type' => 'locality',
        ]);
    }

    private function createPollingCenter(
        Tenant $tenant,
        Area $area,
        string $name,
        string $code
    ): PollingCenter {
        return PollingCenter::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'area_id' => $area->id,
            'name_en' => $name,
            'name_ar' => $name,
            'code' => $code,
        ]);
    }
}
