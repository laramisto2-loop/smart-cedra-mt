<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Governorate;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLoggingTest extends TestCase
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

    public function test_authenticated_create_action_is_audited(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $admin = $this->findUser('admin@cedra.test');

        $response = $this->actingAs($admin)
            ->postJson('/api/governorates', [
                'name_en' => 'Beirut',
                'name_ar' => 'بيروت',
                'code' => 'BEY',
            ])
            ->assertCreated();

        $governorateId = $response->json('data.id');
        $auditLog = AuditLog::query()->sole();

        $this->assertSame($tenant->id, $auditLog->tenant_id);
        $this->assertSame($admin->id, $auditLog->user_id);
        $this->assertSame('created', $auditLog->action);
        $this->assertSame(
            Governorate::class,
            $auditLog->auditable_type
        );
        $this->assertSame(
            $governorateId,
            $auditLog->auditable_id
        );
        $this->assertNull($auditLog->old_values);
        $this->assertSame(
            'Beirut',
            $auditLog->new_values['name_en']
        );
        $this->assertSame(
            'BEY',
            $auditLog->new_values['code']
        );
        $this->assertArrayNotHasKey(
            'created_at',
            $auditLog->new_values
        );

        $this->assertTrue(
            $auditLog->auditable->is(
                Governorate::findOrFail($governorateId)
            )
        );
    }

    public function test_authenticated_update_records_old_and_new_values(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $governorate = $this->createGovernorate(
            $tenant,
            'Beirut',
            'BEY'
        );

        $this->actingAs($this->findUser('admin@cedra.test'))
            ->patchJson(
                "/api/governorates/{$governorate->id}",
                ['name_en' => 'Beirut Updated']
            )
            ->assertOk();

        $auditLog = AuditLog::query()->sole();

        $this->assertSame('updated', $auditLog->action);
        $this->assertSame(
            'Beirut',
            $auditLog->old_values['name_en']
        );
        $this->assertSame(
            'Beirut Updated',
            $auditLog->new_values['name_en']
        );
        $this->assertArrayNotHasKey(
            'code',
            $auditLog->old_values
        );
        $this->assertArrayNotHasKey(
            'updated_at',
            $auditLog->new_values
        );
    }

    public function test_authenticated_delete_records_previous_values(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $governorate = $this->createGovernorate(
            $tenant,
            'Beirut',
            'BEY'
        );

        $this->actingAs($this->findUser('admin@cedra.test'))
            ->deleteJson("/api/governorates/{$governorate->id}")
            ->assertNoContent();

        $auditLog = AuditLog::query()->sole();

        $this->assertSame('deleted', $auditLog->action);
        $this->assertSame(
            $governorate->id,
            $auditLog->auditable_id
        );
        $this->assertSame(
            'Beirut',
            $auditLog->old_values['name_en']
        );
        $this->assertSame(
            'BEY',
            $auditLog->old_values['code']
        );
        $this->assertNull($auditLog->new_values);
    }

    public function test_unauthenticated_system_changes_are_not_audited(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $governorate = $this->createGovernorate(
            $tenant,
            'Beirut',
            'BEY'
        );

        $governorate->update([
            'name_en' => 'System Update',
        ]);

        $governorate->delete();

        $this->assertSame(
            0,
            AuditLog::withoutGlobalScopes()->count()
        );
    }

    public function test_tenant_only_queries_its_own_audit_logs(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');

        $this->actingAs($this->findUser('admin@cedra.test'))
            ->postJson('/api/governorates', [
                'name_en' => 'Cedra Governorate',
                'name_ar' => 'محافظة سيدرا',
                'code' => 'CED',
            ])
            ->assertCreated();

        $this->actingAs($this->findUser('admin@future.test'))
            ->postJson('/api/governorates', [
                'name_en' => 'Future Governorate',
                'name_ar' => 'محافظة المستقبل',
                'code' => 'FUT',
            ])
            ->assertCreated();

        $this->assertSame(1, AuditLog::query()->count());

        $visibleLog = AuditLog::query()->sole();

        $this->assertSame(
            $futureTenant->id,
            $visibleLog->tenant_id
        );
        $this->assertNotSame(
            $cedraTenant->id,
            $visibleLog->tenant_id
        );

        $this->assertSame(
            2,
            AuditLog::withoutGlobalScopes()->count()
        );
    }

    public function test_audit_log_cannot_be_updated(): void
    {
        $this->actingAs($this->findUser('admin@cedra.test'))
            ->postJson('/api/governorates', [
                'name_en' => 'Beirut',
                'name_ar' => 'بيروت',
                'code' => 'BEY',
            ])
            ->assertCreated();

        $auditLog = AuditLog::query()->sole();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'Audit logs are immutable.'
        );

        $auditLog->update([
            'action' => 'tampered',
        ]);
    }

    public function test_audit_log_cannot_be_deleted(): void
    {
        $this->actingAs($this->findUser('admin@cedra.test'))
            ->postJson('/api/governorates', [
                'name_en' => 'Beirut',
                'name_ar' => 'بيروت',
                'code' => 'BEY',
            ])
            ->assertCreated();

        $auditLog = AuditLog::query()->sole();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'Audit logs are immutable.'
        );

        $auditLog->delete();
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
