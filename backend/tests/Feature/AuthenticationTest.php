<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
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

    public function test_spa_can_receive_a_csrf_cookie(): void
    {
        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ])
            ->get('/sanctum/csrf-cookie')
            ->assertNoContent()
            ->assertCookie('XSRF-TOKEN');
    }

    public function test_active_tenant_user_can_login_and_access_protected_api(): void
    {
        $admin = $this->findUser('admin@cedra.test');

        $response = $this->postJson('/login', [
            'email' => 'admin@cedra.test',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $admin->id)
            ->assertJsonPath('data.name', 'Cedra Admin')
            ->assertJsonPath(
                'data.tenant.slug',
                'cedra-campaign'
            )
            ->assertJsonFragment([
                'slug' => 'tenant_admin',
            ]);

        $this->assertAuthenticatedAs($admin);

        $this->assertContains(
            'audit.view',
            $response->json('data.permissions')
        );

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.id', $admin->id)
            ->assertJsonPath(
                'data.tenant.slug',
                'cedra-campaign'
            );

        $this->getJson('/api/governorates')
            ->assertOk();
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $this->postJson('/login', [
            'email' => 'admin@cedra.test',
            'password' => 'incorrect-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);

        $this->assertGuest();
    }

    public function test_user_from_inactive_tenant_cannot_login(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $tenant->update([
            'status' => 'inactive',
        ]);

        $this->postJson('/login', [
            'email' => 'admin@cedra.test',
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);

        $this->assertGuest();
    }

    public function test_user_without_tenant_cannot_login(): void
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'email' => 'orphan@example.test',
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

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        $credentials = [
            'email' => 'admin@cedra.test',
            'password' => 'incorrect-password',
        ];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/login', $credentials)
                ->assertUnprocessable();
        }

        $response = $this->postJson(
            '/login',
            $credentials
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);

        $this->assertStringContainsString(
            'Too many login attempts',
            $response->json('errors.email.0')
        );
    }

    public function test_unauthenticated_user_cannot_access_current_user(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $this->postJson('/login', [
            'email' => 'admin@cedra.test',
            'password' => 'password',
        ])->assertOk();

        $this->postJson('/logout')
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Logged out successfully.'
            );

        $this->assertGuest();

        $this->getJson('/api/user')
            ->assertUnauthorized();
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
}
