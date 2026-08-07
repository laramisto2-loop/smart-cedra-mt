<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\District;
use App\Models\Governorate;
use App\Models\PollingCenter;
use App\Models\PollingStation;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\GeographySeeder;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class GeographyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            TenantSeeder::class,
            RbacSeeder::class,
            GeographySeeder::class,
        ]);
    }

    public function test_tenant_admin_can_fully_manage_own_governorates(): void
    {
        $admin = User::query()
            ->where('email', 'admin@cedra.test')
            ->firstOrFail();

        $governorate = $this->findGovernorate(
            $admin->tenant_id,
            'LB-BA'
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows('viewAny', Governorate::class)
        );
        $this->assertTrue(
            Gate::forUser($admin)->allows('view', $governorate)
        );
        $this->assertTrue(
            Gate::forUser($admin)->allows('create', Governorate::class)
        );
        $this->assertTrue(
            Gate::forUser($admin)->allows('update', $governorate)
        );
        $this->assertTrue(
            Gate::forUser($admin)->allows('delete', $governorate)
        );
    }

    public function test_coordinator_can_manage_but_not_delete_governorates(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $coordinator = $this->createUserWithRole(
            $tenant,
            'coordinator'
        );

        $governorate = $this->findGovernorate($tenant->id, 'LB-BA');

        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'viewAny',
                Governorate::class
            )
        );
        $this->assertTrue(
            Gate::forUser($coordinator)->allows('view', $governorate)
        );
        $this->assertTrue(
            Gate::forUser($coordinator)->allows(
                'create',
                Governorate::class
            )
        );
        $this->assertTrue(
            Gate::forUser($coordinator)->allows('update', $governorate)
        );
        $this->assertFalse(
            Gate::forUser($coordinator)->allows('delete', $governorate)
        );
    }

    public function test_field_agent_can_only_view_governorates(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $fieldAgent = $this->createUserWithRole(
            $tenant,
            'field_agent'
        );

        $governorate = $this->findGovernorate($tenant->id, 'LB-BA');

        $this->assertTrue(
            Gate::forUser($fieldAgent)->allows(
                'viewAny',
                Governorate::class
            )
        );
        $this->assertTrue(
            Gate::forUser($fieldAgent)->allows('view', $governorate)
        );
        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows(
                'create',
                Governorate::class
            )
        );
        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows('update', $governorate)
        );
        $this->assertFalse(
            Gate::forUser($fieldAgent)->allows('delete', $governorate)
        );
    }

    public function test_admin_cannot_manage_another_tenants_governorate(): void
    {
        $admin = User::query()
            ->where('email', 'admin@cedra.test')
            ->firstOrFail();

        $otherTenant = $this->findTenant('lebanon-future');

        $otherGovernorate = $this->findGovernorate(
            $otherTenant->id,
            'LB-BA'
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows('view', $otherGovernorate)
        );
        $this->assertFalse(
            Gate::forUser($admin)->allows('update', $otherGovernorate)
        );
        $this->assertFalse(
            Gate::forUser($admin)->allows('delete', $otherGovernorate)
        );
    }

    public function test_all_geography_models_use_shared_policy(): void
    {
        $admin = User::query()
            ->where('email', 'admin@cedra.test')
            ->firstOrFail();

        $modelClasses = [
            Governorate::class,
            District::class,
            Area::class,
            PollingCenter::class,
            PollingStation::class,
        ];

        foreach ($modelClasses as $modelClass) {
            $this->assertTrue(
                Gate::forUser($admin)->allows('viewAny', $modelClass)
            );

            $this->assertTrue(
                Gate::forUser($admin)->allows('create', $modelClass)
            );
        }

        foreach ($this->findGeographyModels($admin->tenant_id) as $model) {
            $this->assertTrue(
                Gate::forUser($admin)->allows('view', $model)
            );

            $this->assertTrue(
                Gate::forUser($admin)->allows('update', $model)
            );

            $this->assertTrue(
                Gate::forUser($admin)->allows('delete', $model)
            );
        }
    }

    public function test_shared_policy_blocks_all_cross_tenant_models(): void
    {
        $admin = User::query()
            ->where('email', 'admin@cedra.test')
            ->firstOrFail();

        $otherTenant = $this->findTenant('lebanon-future');

        foreach ($this->findGeographyModels($otherTenant->id) as $model) {
            $this->assertFalse(
                Gate::forUser($admin)->allows('view', $model)
            );

            $this->assertFalse(
                Gate::forUser($admin)->allows('update', $model)
            );

            $this->assertFalse(
                Gate::forUser($admin)->allows('delete', $model)
            );
        }

    }

    private function findGeographyModels(int $tenantId): array
    {
        return [
            Governorate::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->firstOrFail(),

            District::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->firstOrFail(),

            Area::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->firstOrFail(),

            PollingCenter::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->firstOrFail(),

            PollingStation::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->firstOrFail(),
        ];
    }

    private function findTenant(string $slug): Tenant
    {
        return Tenant::query()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    private function findGovernorate(
        int $tenantId,
        string $code
    ): Governorate {
        return Governorate::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->firstOrFail();
    }

    private function createUserWithRole(
        Tenant $tenant,
        string $roleSlug
    ): User {
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $role = Role::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $roleSlug)
            ->firstOrFail();

        $user->assignRole($role);

        return $user;
    }
}
