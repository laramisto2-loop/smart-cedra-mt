<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Governorate;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogApiTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_audit_log_api(): void
    {
        $this->getJson('/api/audit-logs')
            ->assertUnauthorized();
    }

    public function test_tenant_admin_only_receives_and_views_own_audit_logs(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');

        $cedraAdmin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $cedraGovernorateId = $this->createGovernorateThroughApi(
            $cedraAdmin,
            'CED'
        );

        $cedraLog = AuditLog::withoutGlobalScopes()
            ->where('tenant_id', $cedraTenant->id)
            ->where('auditable_id', $cedraGovernorateId)
            ->sole();

        $futureGovernorateId = $this->createGovernorateThroughApi(
            $futureAdmin,
            'FUT'
        );

        $futureLog = AuditLog::withoutGlobalScopes()
            ->where('tenant_id', $futureTenant->id)
            ->where('auditable_id', $futureGovernorateId)
            ->sole();

        $response = $this->actingAs($cedraAdmin)
            ->getJson('/api/audit-logs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $cedraLog->id)
            ->assertJsonPath('data.0.action', 'created')
            ->assertJsonPath(
                'data.0.auditable_type',
                Governorate::class
            )
            ->assertJsonPath(
                'data.0.user.email',
                'admin@cedra.test'
            );

        $this->assertSame(
            [$cedraLog->id],
            collect($response->json('data'))
                ->pluck('id')
                ->all()
        );

        $this->getJson("/api/audit-logs/{$cedraLog->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $cedraLog->id)
            ->assertJsonPath('data.action', 'created');

        $this->getJson("/api/audit-logs/{$futureLog->id}")
            ->assertNotFound();
    }

    public function test_admin_can_filter_and_paginate_audit_logs(): void
    {
        $admin = $this->findUser('admin@cedra.test');

        $governorateId = $this->createGovernorateThroughApi(
            $admin,
            'BEY'
        );

        $this->patchJson(
            "/api/governorates/{$governorateId}",
            ['name_en' => 'Beirut Updated']
        )->assertOk();

        $this->getJson('/api/audit-logs?action=updated')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.action', 'updated')
            ->assertJsonPath(
                'data.0.old_values.name_en',
                'BEY Governorate'
            )
            ->assertJsonPath(
                'data.0.new_values.name_en',
                'Beirut Updated'
            );

        $this->getJson(
            "/api/audit-logs?auditable_id={$governorateId}&per_page=1"
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1);
    }

    public function test_users_without_audit_permission_are_forbidden(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $coordinator = $this->createUserWithRole(
            $tenant,
            'coordinator'
        );

        $fieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $this->actingAs($coordinator)
            ->getJson('/api/audit-logs')
            ->assertForbidden();

        $this->actingAs($fieldAgent)
            ->getJson('/api/audit-logs')
            ->assertForbidden();
    }

    public function test_invalid_audit_log_filters_are_rejected(): void
    {
        $admin = $this->findUser('admin@cedra.test');

        $this->actingAs($admin)
            ->getJson(
                '/api/audit-logs'
                .'?per_page=101'
                .'&date_from=2026-08-05'
                .'&date_to=2026-08-04'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page',
                'date_to',
            ]);
    }

    public function test_audit_log_api_has_no_write_routes(): void
    {
        $admin = $this->findUser('admin@cedra.test');

        $governorateId = $this->createGovernorateThroughApi(
            $admin,
            'BEY'
        );

        $auditLog = AuditLog::query()
            ->where('auditable_id', $governorateId)
            ->sole();

        $this->postJson('/api/audit-logs', [])
            ->assertStatus(405);

        $this->patchJson(
            "/api/audit-logs/{$auditLog->id}",
            ['action' => 'tampered']
        )->assertStatus(405);

        $this->deleteJson("/api/audit-logs/{$auditLog->id}")
            ->assertStatus(405);
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

    private function findRole(
        Tenant $tenant,
        string $slug
    ): Role {
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

    private function createGovernorateThroughApi(
        User $user,
        string $code
    ): int {
        $response = $this->actingAs($user)
            ->postJson('/api/governorates', [
                'name_en' => "{$code} Governorate",
                'name_ar' => "{$code} Governorate",
                'code' => $code,
            ])
            ->assertCreated();

        return (int) $response->json('data.id');
    }
}
