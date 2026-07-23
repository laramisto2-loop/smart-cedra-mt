<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_resolves_their_own_tenant_from_the_tenant_context(): void
    {
        $tenant = Tenant::create([
            'name' => 'Cedra Campaign',
            'slug' => 'cedra-campaign',
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($user);

        $context = app(TenantContext::class);

        $this->assertTrue($context->tenant()->is($tenant));
        $this->assertSame($tenant->id, $context->id());
    }

    public function test_unauthenticated_user_cannot_access_tenant_route(): void
    {
        $response = $this->getJson('/tenant-check');

        $response
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_user_without_tenant_cannot_access_tenant_route(): void
    {
        $user = User::factory()->create([
            'tenant_id' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/tenant-check');

        $response
            ->assertStatus(403)
            ->assertJson([
                'message' => 'This user is not assigned to a tenant.',
            ]);
    }

    public function test_user_with_tenant_can_access_own_tenant_information(): void
    {
        $tenant = Tenant::create([
            'name' => 'Cedra Campaign',
            'slug' => 'cedra-campaign',
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/tenant-check');

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Tenant access granted.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => 'Cedra Campaign',
                    'slug' => 'cedra-campaign',
                ],
            ]);
    }

    public function test_authenticated_user_receives_only_their_own_tenant(): void
    {
        $tenantA = Tenant::create([
            'name' => 'Cedra Campaign',
            'slug' => 'cedra-campaign',
            'status' => 'active',
        ]);

        $tenantB = Tenant::create([
            'name' => 'Lebanon Future Campaign',
            'slug' => 'lebanon-future',
            'status' => 'active',
        ]);

        $userA = User::factory()->create([
            'tenant_id' => $tenantA->id,
        ]);

        $response = $this
            ->actingAs($userA)
            ->getJson('/tenant-check');

        $response
            ->assertOk()
            ->assertJsonPath('tenant.id', $tenantA->id)
            ->assertJsonPath('tenant.slug', 'cedra-campaign')
            ->assertJsonMissing([
                'id' => $tenantB->id,
                'slug' => 'lebanon-future',
            ]);
    }

    public function test_tenant_cannot_access_another_tenants_resources(): void
    {
        $tenantA = Tenant::create([
            'name' => 'Cedra Campaign',
            'slug' => 'cedra-campaign',
            'status' => 'active',
        ]);

        $tenantB = Tenant::create([
            'name' => 'Lebanon Future Campaign',
            'slug' => 'lebanon-future',
            'status' => 'active',
        ]);

        $settingA = TenantSetting::create([
            'tenant_id' => $tenantA->id,
            'brand_name' => 'Cedra Campaign',
        ]);

        $settingB = TenantSetting::create([
            'tenant_id' => $tenantB->id,
            'brand_name' => 'Lebanon Future',
        ]);

        $userA = User::factory()->create([
            'tenant_id' => $tenantA->id,
        ]);

        $this->actingAs($userA);

        $this->assertTrue(TenantSetting::findOrFail($settingA->id)->is($settingA));
        $this->assertNull(TenantSetting::find($settingB->id));

        $createdSetting = TenantSetting::create([
            'tenant_id' => $tenantB->id,
            'brand_name' => 'Attempted cross-tenant setting',
        ]);

        $this->assertSame($tenantA->id, $createdSetting->tenant_id);
    }
}
