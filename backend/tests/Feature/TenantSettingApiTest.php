<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSettingApiTest extends TestCase
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

    public function test_tenant_admin_can_view_and_update_own_settings(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $otherTenant = $this->findTenant('lebanon-future');
        $otherSettings = $otherTenant->settings()->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/api/tenant-settings')
            ->assertOk()
            ->assertJsonPath('data.brand_name', 'Cedra Campaign')
            ->assertJsonPath('data.timezone', 'Asia/Beirut');

        $this->actingAs($admin)
            ->patchJson('/api/tenant-settings', [
                'brand_name' => 'Beirut Civic Campaign',
                'primary_color' => '#1387B8',
                'timezone' => 'Europe/Paris',
            ])
            ->assertOk()
            ->assertJsonPath(
                'data.brand_name',
                'Beirut Civic Campaign'
            )
            ->assertJsonPath('data.primary_color', '#1387B8')
            ->assertJsonPath('data.timezone', 'Europe/Paris');

        $this->assertDatabaseHas('tenant_settings', [
            'tenant_id' => $admin->tenant_id,
            'brand_name' => 'Beirut Civic Campaign',
            'primary_color' => '#1387B8',
            'timezone' => 'Europe/Paris',
        ]);

        $this->assertDatabaseHas('tenant_settings', [
            'id' => $otherSettings->id,
            'tenant_id' => $otherTenant->id,
            'brand_name' => 'Lebanon Future',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $admin->tenant_id,
            'user_id' => $admin->id,
            'action' => 'updated',
            'auditable_type' => TenantSetting::class,
        ]);
    }

    public function test_non_admin_cannot_access_tenant_settings(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $coordinator = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $coordinator->assignRole($this->findRole($tenant, 'coordinator'));

        $this->actingAs($coordinator)
            ->getJson('/api/tenant-settings')
            ->assertForbidden();

        $this->actingAs($coordinator)
            ->patchJson('/api/tenant-settings', [
                'brand_name' => 'Unauthorized change',
            ])
            ->assertForbidden();
    }

    public function test_settings_validation_rejects_unsafe_fields(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $otherTenant = $this->findTenant('lebanon-future');

        $this->actingAs($admin)
            ->patchJson('/api/tenant-settings', [
                'primary_color' => 'blue',
                'timezone' => 'Not/A_Timezone',
                'tenant_id' => $otherTenant->id,
                'logo_path' => '../../private-file',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'primary_color',
                'timezone',
                'tenant_id',
                'logo_path',
            ]);
    }

    public function test_authenticated_user_includes_settings_permission_and_values(): void
    {
        $admin = $this->findUser('admin@cedra.test');

        $response = $this->actingAs($admin)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath(
                'data.tenant.settings.brand_name',
                'Cedra Campaign'
            )
            ->assertJsonPath(
                'data.tenant.settings.primary_color',
                '#0d6efd'
            );

        $this->assertContains(
            'settings.manage',
            $response->json('data.permissions')
        );
    }

    private function findTenant(string $slug): Tenant
    {
        return Tenant::query()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    private function findUser(string $email): User
    {
        return User::query()
            ->where('email', $email)
            ->firstOrFail();
    }

    private function findRole(Tenant $tenant, string $slug): Role
    {
        return Role::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->firstOrFail();
    }
}
