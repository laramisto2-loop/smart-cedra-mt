<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlatformAdministratorSeeder;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformTenantManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            TenantSeeder::class,
            PlatformAdministratorSeeder::class,
            RbacSeeder::class,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_platform_tenants(): void
    {
        $this->getJson('/api/platform/tenants')
            ->assertUnauthorized();
    }

    public function test_tenant_administrator_cannot_manage_platform_tenants(): void
    {
        $tenantAdministrator = User::query()
            ->where('email', 'admin@cedra.test')
            ->firstOrFail();

        $this->actingAs($tenantAdministrator)
            ->getJson('/api/platform/tenants')
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Platform administrator access is required.'
            );
    }

    public function test_platform_administrator_lists_and_searches_tenants(): void
    {
        $platformAdministrator = $this->platformAdministrator();

        $this->actingAs($platformAdministrator)
            ->getJson('/api/platform/tenants')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Cedra Campaign')
            ->assertJsonPath(
                'data.1.name',
                'Lebanon Future Campaign'
            );

        $this->actingAs($platformAdministrator)
            ->getJson(
                '/api/platform/tenants?search=Future&status=active'
            )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.slug',
                'lebanon-future'
            );
    }

    public function test_platform_administrator_creates_complete_tenant_account(): void
    {
        $platformAdministrator = $this->platformAdministrator();

        $response = $this->actingAs($platformAdministrator)
            ->postJson('/api/platform/tenants', [
                'name' => 'Beirut Reform Campaign',
                'slug' => 'beirut-reform',
                'status' => 'active',
                'brand_name' => 'Beirut Reform 2026',
                'primary_color' => '#123456',
                'timezone' => 'Asia/Beirut',
                'admin_name' => 'Beirut Reform Admin',
                'admin_email' => 'admin@beirut-reform.test',
                'admin_password' => 'SecurePassword123!',
                'admin_password_confirmation' => 'SecurePassword123!',
            ])
            ->assertCreated()
            ->assertJsonPath(
                'data.name',
                'Beirut Reform Campaign'
            )
            ->assertJsonPath(
                'data.slug',
                'beirut-reform'
            )
            ->assertJsonPath(
                'data.status',
                'active'
            )
            ->assertJsonPath(
                'data.settings.brand_name',
                'Beirut Reform 2026'
            )
            ->assertJsonPath(
                'data.settings.primary_color',
                '#123456'
            )
            ->assertJsonPath(
                'data.settings.timezone',
                'Asia/Beirut'
            )
            ->assertJsonPath(
                'data.administrator_count',
                1
            )
            ->assertJsonPath(
                'data.administrators.0.email',
                'admin@beirut-reform.test'
            );

        $tenantId = $response->json('data.id');

        $tenant = Tenant::query()->findOrFail($tenantId);

        $administrator = User::query()
            ->where(
                'email',
                'admin@beirut-reform.test'
            )
            ->firstOrFail();

        $this->assertSame(
            $tenant->id,
            $administrator->tenant_id
        );

        $this->assertTrue(
            $administrator->hasRole('tenant_admin')
        );

        $this->assertTrue(
            Hash::check(
                'SecurePassword123!',
                $administrator->password
            )
        );

        $this->assertDatabaseHas('tenant_settings', [
            'tenant_id' => $tenant->id,
            'brand_name' => 'Beirut Reform 2026',
            'primary_color' => '#123456',
            'timezone' => 'Asia/Beirut',
        ]);

        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        $this->postJson('/login', [
            'email' => 'admin@beirut-reform.test',
            'password' => 'SecurePassword123!',
        ])
            ->assertOk()
            ->assertJsonPath(
                'data.tenant.slug',
                'beirut-reform'
            );

        $this->assertAuthenticatedAs($administrator);
    }

    public function test_platform_administrator_views_and_updates_tenant_configuration(): void
    {
        $platformAdministrator = $this->platformAdministrator();

        $tenant = Tenant::query()
            ->where('slug', 'cedra-campaign')
            ->firstOrFail();

        $this->actingAs($platformAdministrator)
            ->getJson("/api/platform/tenants/{$tenant->id}")
            ->assertOk()
            ->assertJsonPath(
                'data.slug',
                'cedra-campaign'
            )
            ->assertJsonPath(
                'data.administrators.0.email',
                'admin@cedra.test'
            );

        $this->actingAs($platformAdministrator)
            ->patchJson(
                "/api/platform/tenants/{$tenant->id}",
                [
                    'name' => 'Cedra National Campaign',
                    'slug' => 'cedra-national',
                    'brand_name' => 'Cedra National',
                    'primary_color' => '#0099AA',
                    'timezone' => 'Asia/Beirut',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Cedra National Campaign'
            )
            ->assertJsonPath(
                'data.slug',
                'cedra-national'
            )
            ->assertJsonPath(
                'data.settings.brand_name',
                'Cedra National'
            )
            ->assertJsonPath(
                'data.settings.primary_color',
                '#0099AA'
            );

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'Cedra National Campaign',
            'slug' => 'cedra-national',
        ]);

        $this->assertDatabaseHas('tenant_settings', [
            'tenant_id' => $tenant->id,
            'brand_name' => 'Cedra National',
            'primary_color' => '#0099AA',
            'timezone' => 'Asia/Beirut',
        ]);
    }

    public function test_tenant_status_can_be_suspended_and_reactivated(): void
    {
        $platformAdministrator = $this->platformAdministrator();

        $tenant = Tenant::query()
            ->where('slug', 'cedra-campaign')
            ->firstOrFail();

        $tenantAdministrator = User::query()
            ->where('email', 'admin@cedra.test')
            ->firstOrFail();

        $tenantAdministrator->createToken('browser');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $tenantAdministrator->id,
        ]);

        $this->actingAs($platformAdministrator)
            ->patchJson(
                "/api/platform/tenants/{$tenant->id}/status",
                [
                    'status' => 'suspended',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'suspended'
            );

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'status' => 'suspended',
        ]);

        $this->assertDatabaseMissing(
            'personal_access_tokens',
            [
                'tokenable_type' => User::class,
                'tokenable_id' => $tenantAdministrator->id,
            ]
        );

        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        $this->postJson('/login', [
            'email' => 'admin@cedra.test',
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);

        $this->assertGuest();

        $this->actingAs($platformAdministrator)
            ->patchJson(
                "/api/platform/tenants/{$tenant->id}/status",
                [
                    'status' => 'active',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'active'
            );

        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        $this->postJson('/login', [
            'email' => 'admin@cedra.test',
            'password' => 'password',
        ])
            ->assertOk();

        $this->assertAuthenticatedAs($tenantAdministrator);
    }

    public function test_platform_tenant_validation_protects_unique_and_internal_fields(): void
    {
        $platformAdministrator = $this->platformAdministrator();

        $this->actingAs($platformAdministrator)
            ->postJson('/api/platform/tenants', [
                'name' => 'Duplicate Campaign',
                'slug' => 'cedra-campaign',
                'admin_name' => 'Duplicate Admin',
                'admin_email' => 'admin@cedra.test',
                'admin_password' => 'SecurePassword123!',
                'admin_password_confirmation' => 'SecurePassword123!',
                'tenant_id' => 999,
                'is_platform_admin' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'slug',
                'admin_email',
                'tenant_id',
                'is_platform_admin',
            ]);

        $tenant = Tenant::query()
            ->where('slug', 'cedra-campaign')
            ->firstOrFail();

        $this->actingAs($platformAdministrator)
            ->patchJson(
                "/api/platform/tenants/{$tenant->id}",
                [
                    'status' => 'suspended',
                    'admin_email' => 'replacement@example.test',
                    'is_platform_admin' => true,
                    'logo_path' => 'unsafe/logo.png',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
                'admin_email',
                'is_platform_admin',
                'logo_path',
            ]);
    }

    public function test_invalid_status_and_filters_are_rejected(): void
    {
        $platformAdministrator = $this->platformAdministrator();

        $tenant = Tenant::query()
            ->where('slug', 'cedra-campaign')
            ->firstOrFail();

        $this->actingAs($platformAdministrator)
            ->getJson(
                '/api/platform/tenants?status=deleted'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);

        $this->actingAs($platformAdministrator)
            ->patchJson(
                "/api/platform/tenants/{$tenant->id}/status",
                [
                    'status' => 'deleted',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);
    }

    private function platformAdministrator(): User
    {
        return User::query()
            ->where(
                'email',
                'platform@electoflow.test'
            )
            ->firstOrFail();
    }
}
