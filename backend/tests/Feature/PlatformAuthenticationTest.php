<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PlatformAdministratorSeeder;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAuthenticationTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_platform_api(): void
    {
        $this->getJson('/api/platform/user')
            ->assertUnauthorized();
    }

    public function test_platform_administrator_can_login_and_access_platform_api(): void
    {
        $platformAdministrator = $this->platformAdministrator();

        $this->postJson('/login', [
            'email' => 'platform@electoflow.test',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $platformAdministrator->id
            )
            ->assertJsonPath(
                'data.is_platform_admin',
                true
            )
            ->assertJsonPath(
                'data.tenant',
                null
            );

        $this->assertAuthenticatedAs(
            $platformAdministrator
        );

        $this->getJson('/api/platform/user')
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $platformAdministrator->id
            )
            ->assertJsonPath(
                'data.is_platform_admin',
                true
            );
    }

    public function test_tenant_administrator_cannot_access_platform_api(): void
    {
        $tenantAdministrator = User::query()
            ->where('email', 'admin@cedra.test')
            ->firstOrFail();

        $this->actingAs($tenantAdministrator)
            ->getJson('/api/platform/user')
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Platform administrator access is required.'
            );
    }

    public function test_platform_administrator_cannot_access_tenant_api(): void
    {
        $this->actingAs(
            $this->platformAdministrator()
        )
            ->getJson('/api/user')
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'This user is not assigned to a tenant.'
            );
    }

    public function test_ordinary_tenantless_user_still_cannot_login(): void
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'is_platform_admin' => false,
            'email' => 'orphan@example.test',
            'password' => 'password',
        ]);

        $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);

        $this->assertGuest();
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
