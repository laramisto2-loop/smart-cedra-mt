<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

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
}