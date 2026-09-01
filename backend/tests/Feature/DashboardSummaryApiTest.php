<?php

namespace Tests\Feature;

use App\Models\CallAssignment;
use App\Models\CampaignTask;
use App\Models\Contact;
use App\Models\ContactConsent;
use App\Models\Incident;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardSummaryApiTest extends TestCase
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

    public function test_summary_uses_real_tenant_scoped_data(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $otherAdmin = $this->findUser('admin@future.test');
        $this->actingAs($admin);

        $contacts = collect([
            $this->createContact($admin, 'DASH-001'),
            $this->createContact($admin, 'DASH-002'),
            $this->createContact(
                $admin,
                'DASH-003',
                ['status' => 'inactive']
            ),
        ]);

        ContactConsent::create([
            'contact_id' => $contacts[0]->id,
            'recorded_by_user_id' => $admin->id,
            'channel' => 'phone',
            'status' => 'granted',
            'consented_at' => now(),
        ]);

        foreach (CampaignTask::STATUSES as $status) {
            CampaignTask::create([
                'created_by_user_id' => $admin->id,
                'assigned_to_user_id' => $admin->id,
                'title' => "Dashboard {$status} task",
                'status' => $status,
            ]);
        }

        foreach (Incident::STATUSES as $status) {
            Incident::create([
                'reported_by_user_id' => $admin->id,
                'assigned_to_user_id' => $admin->id,
                'title' => "Dashboard {$status} incident",
                'description' => 'Synthetic dashboard test incident.',
                'severity' => $status === 'submitted'
                    ? 'critical'
                    : 'medium',
                'status' => $status,
                'occurred_at' => now(),
            ]);
        }

        $this->insertMessages($admin);
        $this->insertCallActivity($admin, $contacts);

        DB::table('contacts')->insert([
            'tenant_id' => $otherAdmin->tenant_id,
            'created_by_user_id' => $otherAdmin->id,
            'reference_code' => 'OTHER-DASH-001',
            'first_name' => 'Other',
            'last_name' => 'Tenant',
            'preferred_language' => 'en',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->getJson('/api/dashboard-summary')
            ->assertOk()
            ->assertJsonPath('data.contacts.total', 3)
            ->assertJsonPath('data.contacts.active', 2)
            ->assertJsonPath(
                'data.contacts.with_granted_consent',
                1
            )
            ->assertJsonPath(
                'data.contacts.consent_coverage_rate',
                50
            )
            ->assertJsonPath('data.tasks.total', 4)
            ->assertJsonPath('data.tasks.open', 2)
            ->assertJsonPath('data.tasks.completion_rate', 33.3)
            ->assertJsonPath('data.incidents.open', 2)
            ->assertJsonPath('data.incidents.critical_open', 1)
            ->assertJsonPath('data.incidents.closed_rate', 50)
            ->assertJsonPath('data.messages.total', 3)
            ->assertJsonPath('data.messages.delivery_rate', 66.7)
            ->assertJsonPath('data.calls.total', 3)
            ->assertJsonPath('data.calls.open', 1)
            ->assertJsonPath('data.calls.attempts', 1)
            ->assertJsonPath('data.calls.completion_rate', 50);
    }

    public function test_field_agent_only_sees_accessible_work(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $tenant = $admin->tenant;
        $fieldAgent = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $fieldAgent->assignRole(
            $this->findRole($tenant, 'field_agent')
        );
        $this->actingAs($admin);

        CampaignTask::create([
            'created_by_user_id' => $admin->id,
            'assigned_to_user_id' => $fieldAgent->id,
            'title' => 'Field agent task',
            'status' => 'pending',
        ]);
        CampaignTask::create([
            'created_by_user_id' => $admin->id,
            'assigned_to_user_id' => $admin->id,
            'title' => 'Administrator task',
            'status' => 'completed',
        ]);
        Incident::create([
            'reported_by_user_id' => $fieldAgent->id,
            'title' => 'Field agent incident',
            'description' => 'Accessible incident.',
            'status' => 'submitted',
            'occurred_at' => now(),
        ]);
        Incident::create([
            'reported_by_user_id' => $admin->id,
            'title' => 'Administrator incident',
            'description' => 'Inaccessible incident.',
            'status' => 'resolved',
            'occurred_at' => now(),
        ]);

        $this->actingAs($fieldAgent)
            ->getJson('/api/dashboard-summary')
            ->assertOk()
            ->assertJsonPath('data.contacts', null)
            ->assertJsonPath('data.messages', null)
            ->assertJsonPath('data.tasks.total', 1)
            ->assertJsonPath('data.tasks.open', 1)
            ->assertJsonPath('data.incidents.total', 1)
            ->assertJsonPath('data.incidents.open', 1);
    }

    public function test_summary_requires_authentication(): void
    {
        $this->getJson('/api/dashboard-summary')
            ->assertUnauthorized();
    }

    private function insertMessages(User $admin): void
    {
        foreach (['delivered', 'read', 'failed'] as $index => $status) {
            DB::table('outbound_messages')->insert([
                'tenant_id' => $admin->tenant_id,
                'sent_by_user_id' => $admin->id,
                'client_uuid' => Str::uuid()->toString(),
                'reference_code' => "DASH-MSG-{$index}",
                'channel' => 'sms',
                'recipient' => "+9617000000{$index}",
                'rendered_body' => 'Synthetic dashboard test message.',
                'source' => 'manual',
                'status' => $status,
                'consent_status' => 'granted',
                'consent_checked_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function insertCallActivity(
        User $admin,
        $contacts
    ): void {
        $queueId = DB::table('call_queues')->insertGetId([
            'tenant_id' => $admin->tenant_id,
            'created_by_user_id' => $admin->id,
            'name' => 'Dashboard test queue',
            'code' => 'DASHBOARD_TEST_QUEUE',
            'priority' => 'normal',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $assignments = collect(['completed', 'pending', 'skipped'])
            ->map(function (string $status, int $index) use (
                $admin,
                $contacts,
                $queueId
            ): int {
                return CallAssignment::create([
                    'call_queue_id' => $queueId,
                    'contact_id' => $contacts[$index]->id,
                    'assigned_to_user_id' => $admin->id,
                    'assigned_by_user_id' => $admin->id,
                    'status' => $status,
                    'priority' => 'normal',
                ])->id;
            });

        DB::table('call_attempts')->insert([
            'tenant_id' => $admin->tenant_id,
            'call_assignment_id' => $assignments[0],
            'performed_by_user_id' => $admin->id,
            'client_uuid' => Str::uuid()->toString(),
            'reference_code' => 'DASH-CALL-001',
            'outcome' => 'completed',
            'duration_seconds' => 120,
            'attempted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createContact(
        User $creator,
        string $referenceCode,
        array $overrides = []
    ): Contact {
        return Contact::create(array_merge([
            'created_by_user_id' => $creator->id,
            'reference_code' => $referenceCode,
            'first_name' => 'Dashboard',
            'last_name' => 'Contact',
            'preferred_language' => 'en',
            'status' => 'active',
        ], $overrides));
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
