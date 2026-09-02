<?php

namespace Tests\Feature;

use App\Models\Governorate;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GovernorateApiTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_governorate_api(): void
    {
        $this->getJson('/api/governorates')
            ->assertUnauthorized();
    }

    public function test_admin_only_receives_their_own_tenants_governorates(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');

        $this->createGovernorate(
            $cedraTenant,
            'Cedra Governorate',
            'CED'
        );

        $this->createGovernorate(
            $futureTenant,
            'Future Governorate',
            'FUT'
        );

        $admin = User::query()
            ->where('email', 'admin@cedra.test')
            ->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/api/governorates')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'code' => 'CED',
            ])
            ->assertJsonMissing([
                'code' => 'FUT',
            ]);
    }

    public function test_admin_can_search_governorates_by_name_or_code(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $this->createGovernorate($tenant, 'Searchable North', 'SRCH-N');
        $this->createGovernorate($tenant, 'Unrelated South', 'OTHER-S');

        $admin = User::query()
            ->where('email', 'admin@cedra.test')
            ->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/api/governorates?search=SRCH-N')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name_en', 'Searchable North');
    }

    public function test_tenant_admin_can_create_update_and_delete_governorate(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $admin = User::query()
            ->where('email', 'admin@cedra.test')
            ->firstOrFail();

        $createResponse = $this->actingAs($admin)
            ->postJson('/api/governorates', [
                'name_en' => 'Nabatieh',
                'name_ar' => 'النبطية',
                'code' => 'NAB',
            ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.name_en', 'Nabatieh')
            ->assertJsonPath('data.code', 'NAB');

        $governorateId = $createResponse->json('data.id');

        $this->assertDatabaseHas('governorates', [
            'id' => $governorateId,
            'tenant_id' => $tenant->id,
            'code' => 'NAB',
        ]);

        $this->getJson("/api/governorates/{$governorateId}")
            ->assertOk()
            ->assertJsonPath('data.code', 'NAB');

        $this->patchJson("/api/governorates/{$governorateId}", [
            'name_en' => 'Nabatieh Updated',
        ])
            ->assertOk()
            ->assertJsonPath('data.name_en', 'Nabatieh Updated');

        $this->deleteJson("/api/governorates/{$governorateId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('governorates', [
            'id' => $governorateId,
        ]);
    }

    public function test_submitted_tenant_id_is_rejected(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');

        $admin = User::query()
            ->where('email', 'admin@cedra.test')
            ->firstOrFail();

        $this->actingAs($admin)
            ->postJson('/api/governorates', [
                'tenant_id' => $futureTenant->id,
                'name_en' => 'Invalid Governorate',
                'name_ar' => 'محافظة غير صالحة',
                'code' => 'INV',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'tenant_id',
            ]);

        $this->assertDatabaseMissing('governorates', [
            'tenant_id' => $cedraTenant->id,
            'code' => 'INV',
        ]);

        $this->assertDatabaseMissing('governorates', [
            'tenant_id' => $futureTenant->id,
            'code' => 'INV',
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

        $coordinator = $this->createUserWithRole(
            $tenant,
            'coordinator'
        );

        $this->actingAs($coordinator)
            ->postJson('/api/governorates', [
                'name_en' => 'Mount Lebanon',
                'name_ar' => 'جبل لبنان',
                'code' => 'MLB',
            ])
            ->assertCreated();

        $this->patchJson("/api/governorates/{$governorate->id}", [
            'name_en' => 'Beirut Updated',
        ])
            ->assertOk()
            ->assertJsonPath('data.name_en', 'Beirut Updated');

        $this->deleteJson("/api/governorates/{$governorate->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('governorates', [
            'id' => $governorate->id,
        ]);
    }

    public function test_field_agent_can_view_but_cannot_modify_governorates(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $governorate = $this->createGovernorate(
            $tenant,
            'Beirut',
            'BEY'
        );

        $fieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $this->actingAs($fieldAgent)
            ->getJson('/api/governorates')
            ->assertOk();

        $this->getJson("/api/governorates/{$governorate->id}")
            ->assertOk();

        $this->postJson('/api/governorates', [
            'name_en' => 'Forbidden Governorate',
            'name_ar' => 'محافظة ممنوعة',
            'code' => 'FOR',
        ])
            ->assertForbidden();

        $this->patchJson("/api/governorates/{$governorate->id}", [
            'name_en' => 'Forbidden Update',
        ])
            ->assertForbidden();

        $this->deleteJson("/api/governorates/{$governorate->id}")
            ->assertForbidden();
    }

    public function test_governorate_code_must_be_unique_inside_tenant(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');

        $this->createGovernorate(
            $cedraTenant,
            'Cedra Beirut',
            'BEY'
        );

        $this->createGovernorate(
            $futureTenant,
            'Future Beirut',
            'BEY'
        );

        $admin = User::query()
            ->where('email', 'admin@cedra.test')
            ->firstOrFail();

        $this->actingAs($admin)
            ->postJson('/api/governorates', [
                'name_en' => 'Duplicate Beirut',
                'name_ar' => 'بيروت مكررة',
                'code' => 'BEY',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'code',
            ]);
    }

    public function test_admin_cannot_access_another_tenants_governorate(): void
    {
        $futureTenant = $this->findTenant('lebanon-future');

        $futureGovernorate = $this->createGovernorate(
            $futureTenant,
            'Future Governorate',
            'FUT'
        );

        $cedraAdmin = User::query()
            ->where('email', 'admin@cedra.test')
            ->firstOrFail();

        $this->actingAs($cedraAdmin)
            ->getJson("/api/governorates/{$futureGovernorate->id}")
            ->assertNotFound();

        $this->patchJson(
            "/api/governorates/{$futureGovernorate->id}",
            ['name_en' => 'Forbidden Update']
        )
            ->assertNotFound();

        $this->deleteJson(
            "/api/governorates/{$futureGovernorate->id}"
        )
            ->assertNotFound();
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
}
