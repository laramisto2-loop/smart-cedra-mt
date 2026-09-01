<?php

namespace Tests\Feature;

use App\Models\ElectionContest;
use App\Models\ElectionOption;
use App\Models\PollingCenter;
use App\Models\PollingStation;
use App\Models\Role;
use App\Models\TallyResult;
use App\Models\TallySheet;
use App\Models\TallySheetAttachment;
use App\Models\TallySubmission;
use App\Models\Tenant;
use App\Models\User;
use Closure;
use Database\Seeders\GeographySeeder;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class ResultsIngestionFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            TenantSeeder::class,
            GeographySeeder::class,
            RbacSeeder::class,
        ]);
    }

    public function test_results_relationships_and_double_entry_identity_work(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $approver = $this->createUserWithRole(
            $admin->tenant,
            'tenant_admin'
        );
        $firstAgent = $this->createUserWithRole($admin->tenant, 'field_agent');
        $secondAgent = $this->createUserWithRole($admin->tenant, 'field_agent');
        $contest = $this->createContest($admin);
        $firstOption = $this->createOption($admin, $contest, [
            'code' => 'LIST-A',
            'name' => 'List A',
            'ballot_order' => 1,
        ]);
        $secondOption = $this->createOption($admin, $contest, [
            'code' => 'LIST-B',
            'name' => 'List B',
            'ballot_order' => 2,
        ]);
        $sheet = $this->createSheet($firstAgent, $contest);
        $attachment = $this->createAttachment($firstAgent, $sheet);

        $firstEntry = $this->createSubmission($firstAgent, $sheet, [
            'entry_number' => 1,
        ]);
        $this->createResult($firstAgent, $firstEntry, $firstOption, 70);
        $this->createResult($firstAgent, $firstEntry, $secondOption, 30);
        $this->submitEntry($firstAgent, $firstEntry);

        $secondEntry = $this->createSubmission($secondAgent, $sheet, [
            'entry_number' => 2,
        ]);
        $this->createResult($secondAgent, $secondEntry, $firstOption, 70);
        $this->createResult($secondAgent, $secondEntry, $secondOption, 30);
        $this->submitEntry($secondAgent, $secondEntry);

        $this->actingAs($admin);
        $sheet->update([
            'status' => TallySheet::STATUS_READY_FOR_REVIEW,
            'reviewed_by_user_id' => $admin->id,
            'reviewed_at' => now(),
        ]);
        $sheet->update([
            'status' => TallySheet::STATUS_APPROVED,
            'approved_by_user_id' => $approver->id,
            'approved_submission_id' => $firstEntry->id,
        ]);

        $contest->refresh();
        $sheet->refresh();
        $firstEntry->refresh();

        $this->assertSame($admin->tenant_id, $contest->tenant_id);
        $this->assertSame($admin->tenant_id, $sheet->tenant_id);
        $this->assertSame(TallySheet::STATUS_APPROVED, $sheet->status);
        $this->assertNotNull($sheet->approved_at);
        $this->assertMatchesRegularExpression('/^TALLY-[A-F0-9]{12}$/', $sheet->reference_code);
        $this->assertMatchesRegularExpression('/^ENTRY-[A-F0-9]{12}$/', $firstEntry->reference_code);
        $this->assertTrue($contest->creator->is($admin));
        $this->assertCount(2, $contest->options);
        $this->assertTrue($sheet->contest->is($contest));
        $this->assertNotNull($sheet->pollingCenter);
        $this->assertNotNull($sheet->pollingStation);
        $this->assertTrue($sheet->approvedSubmission->is($firstEntry));
        $this->assertCount(2, $sheet->submissions);
        $this->assertCount(1, $sheet->attachments);
        $this->assertTrue($attachment->uploader->is($firstAgent));
        $this->assertTrue($firstEntry->entrant->is($firstAgent));
        $this->assertCount(2, $firstEntry->results);
        $this->assertTrue($firstEntry->results->first()->electionOption->is($firstOption));
    }

    public function test_tenant_only_queries_its_own_results_records(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');

        $this->createResultsGraph($admin);
        $this->createResultsGraph($futureAdmin);

        $this->actingAs($admin);

        foreach ([
            ElectionContest::class,
            ElectionOption::class,
            TallySheet::class,
            TallySubmission::class,
            TallyResult::class,
            TallySheetAttachment::class,
        ] as $modelClass) {
            $this->assertCount(1, $modelClass::query()->get());
            $this->assertSame(
                $admin->tenant_id,
                $modelClass::query()->firstOrFail()->tenant_id
            );
        }
    }

    public function test_active_tenant_overrides_submitted_tenant_id(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');
        $this->actingAs($admin);

        $contest = $this->createContest($admin, [
            'tenant_id' => $futureAdmin->tenant_id,
        ]);
        $option = $this->createOption($admin, $contest, [
            'tenant_id' => $futureAdmin->tenant_id,
        ]);
        $sheet = $this->createSheet($admin, $contest, [
            'tenant_id' => $futureAdmin->tenant_id,
        ]);
        $submission = $this->createSubmission($admin, $sheet, [
            'tenant_id' => $futureAdmin->tenant_id,
        ]);
        $result = $this->createResult($admin, $submission, $option, 100, [
            'tenant_id' => $futureAdmin->tenant_id,
        ]);
        $attachment = $this->createAttachment($admin, $sheet, [
            'tenant_id' => $futureAdmin->tenant_id,
        ]);

        foreach ([$contest, $option, $sheet, $submission, $result, $attachment] as $model) {
            $this->assertSame($admin->tenant_id, $model->tenant_id);
        }
    }

    public function test_results_models_reject_cross_tenant_relationships(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');
        $contest = $this->createContest($admin);

        $this->assertLogicException(
            'The parent geography record must belong to the same tenant.',
            fn () => $this->createOption($futureAdmin, $contest)
        );

        $this->assertLogicException(
            'The tally sheet election contest must belong to the same tenant.',
            fn () => $this->createSheet($futureAdmin, $contest)
        );

        $futureContest = $this->createContest($futureAdmin);
        $futureSheet = $this->createSheet($futureAdmin, $futureContest);

        $this->assertLogicException(
            'The tally submission tally sheet must belong to the same tenant.',
            fn () => $this->createSubmission($admin, $futureSheet)
        );
    }

    public function test_tally_counts_results_and_double_entry_constraints_are_enforced(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $contest = $this->createContest($admin);
        $option = $this->createOption($admin, $contest);
        $sheet = $this->createSheet($admin, $contest);

        $this->assertLogicException(
            'The tally submission entry number must be 1 or 2.',
            fn () => $this->createSubmission($admin, $sheet, ['entry_number' => 3])
        );

        $this->assertLogicException(
            'Ballots cast cannot exceed registered voters.',
            fn () => $this->createSubmission($admin, $sheet, [
                'status' => TallySubmission::STATUS_SUBMITTED,
                'registered_voters' => 99,
                'ballots_cast' => 100,
            ])
        );

        $submission = $this->createSubmission($admin, $sheet);

        $this->assertLogicException(
            'The tally result vote count cannot be negative.',
            fn () => $this->createResult($admin, $submission, $option, -1)
        );

        $this->expectException(QueryException::class);
        $this->createSubmission($admin, $sheet, ['entry_number' => 1]);
    }

    public function test_submitted_entries_and_finalized_sheets_are_immutable(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $reviewer = $this->createUserWithRole(
            $admin->tenant,
            'tenant_admin'
        );
        $approver = $this->createUserWithRole(
            $admin->tenant,
            'tenant_admin'
        );
        $contest = $this->createContest($admin);
        $option = $this->createOption($admin, $contest);
        $sheet = $this->createSheet($admin, $contest);
        $attachment = $this->createAttachment($admin, $sheet);
        $submission = $this->createSubmission($admin, $sheet);
        $result = $this->createResult($admin, $submission, $option, 100);
        $this->submitEntry($admin, $submission);

        $this->assertLogicException(
            'Submitted tally entries are immutable. Record a replacement entry instead.',
            fn () => $submission->update(['notes' => 'Changed'])
        );
        $this->assertLogicException(
            'Results belonging to a submitted tally entry cannot be changed.',
            fn () => $result->update(['votes' => 99])
        );
        $this->assertLogicException(
            'Results belonging to a submitted tally entry cannot be deleted.',
            fn () => $result->delete()
        );

        $this->actingAs($reviewer);
        $sheet->update([
            'status' => TallySheet::STATUS_READY_FOR_REVIEW,
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($approver);
        $sheet->update([
            'status' => TallySheet::STATUS_APPROVED,
            'approved_by_user_id' => $approver->id,
            'approved_submission_id' => $submission->id,
        ]);

        $this->assertLogicException(
            'A finalized tally sheet cannot be modified.',
            fn () => $sheet->update(['notes' => 'Changed'])
        );
        $this->assertLogicException(
            'Attachments belonging to a finalized tally sheet cannot be changed or deleted.',
            fn () => $attachment->delete()
        );
    }

    public function test_policies_enforce_roles_ownership_status_and_tenants(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');
        $coordinator = $this->createUserWithRole($admin->tenant, 'coordinator');
        $fieldAgent = $this->createUserWithRole($admin->tenant, 'field_agent');
        $otherAgent = $this->createUserWithRole($admin->tenant, 'field_agent');
        $contest = $this->createContest($admin, [
            'status' => ElectionContest::STATUS_DRAFT,
        ]);
        $sheet = $this->createSheet($fieldAgent, $contest);
        $submission = $this->createSubmission($fieldAgent, $sheet);

        $this->assertTrue(Gate::forUser($admin)->allows('create', ElectionContest::class));
        $this->assertTrue(Gate::forUser($coordinator)->allows('update', $contest));
        $this->assertFalse(Gate::forUser($fieldAgent)->allows('create', ElectionContest::class));
        $this->assertFalse(Gate::forUser($futureAdmin)->allows('view', $contest));
        $this->assertTrue(Gate::forUser($fieldAgent)->allows('update', $sheet));
        $this->assertFalse(Gate::forUser($otherAgent)->allows('update', $sheet));
        $this->assertTrue(Gate::forUser($fieldAgent)->allows('submit', $submission));
        $this->assertFalse(Gate::forUser($otherAgent)->allows('submit', $submission));

        $this->actingAs($admin);
        $sheet->update(['status' => TallySheet::STATUS_READY_FOR_REVIEW]);

        $this->assertTrue(Gate::forUser($coordinator)->allows('review', $sheet));
        $this->assertFalse(Gate::forUser($fieldAgent)->allows('review', $sheet));
        $this->assertFalse(Gate::forUser($admin)->allows('approve', $sheet));

        $sheet->update([
            'reviewed_by_user_id' => $coordinator->id,
            'reviewed_at' => now(),
        ]);

        $this->assertTrue(Gate::forUser($admin)->allows('approve', $sheet));
    }

    /**
     * @return array{ElectionContest, ElectionOption, TallySheet, TallySubmission, TallyResult, TallySheetAttachment}
     */
    private function createResultsGraph(User $user): array
    {
        $contest = $this->createContest($user);
        $option = $this->createOption($user, $contest);
        $sheet = $this->createSheet($user, $contest);
        $submission = $this->createSubmission($user, $sheet);
        $result = $this->createResult($user, $submission, $option, 100);
        $attachment = $this->createAttachment($user, $sheet);

        return [$contest, $option, $sheet, $submission, $result, $attachment];
    }

    private function createContest(User $user, array $attributes = []): ElectionContest
    {
        $this->actingAs($user);

        return ElectionContest::query()->create(array_merge([
            'tenant_id' => $user->tenant_id,
            'created_by_user_id' => $user->id,
            'code' => 'CONTEST-'.strtoupper(Str::random(8)),
            'name' => 'Parliamentary election',
            'status' => ElectionContest::STATUS_ACTIVE,
            'election_date' => now()->addMonth()->toDateString(),
        ], $attributes));
    }

    private function createOption(User $user, ElectionContest $contest, array $attributes = []): ElectionOption
    {
        $this->actingAs($user);

        return ElectionOption::query()->create(array_merge([
            'tenant_id' => $user->tenant_id,
            'election_contest_id' => $contest->id,
            'code' => 'OPTION-'.strtoupper(Str::random(8)),
            'name' => 'Election option',
            'option_type' => ElectionOption::TYPE_LIST,
            'ballot_order' => 1,
            'is_active' => true,
        ], $attributes));
    }

    private function createSheet(User $user, ElectionContest $contest, array $attributes = []): TallySheet
    {
        $this->actingAs($user);
        [$center, $station] = $this->findGeography($user->tenant_id);

        return TallySheet::query()->create(array_merge([
            'tenant_id' => $user->tenant_id,
            'election_contest_id' => $contest->id,
            'polling_center_id' => $center->id,
            'polling_station_id' => $station->id,
            'created_by_user_id' => $user->id,
            'status' => TallySheet::STATUS_PENDING,
        ], $attributes));
    }

    private function createSubmission(User $user, TallySheet $sheet, array $attributes = []): TallySubmission
    {
        $this->actingAs($user);

        return TallySubmission::query()->create(array_merge([
            'tenant_id' => $user->tenant_id,
            'tally_sheet_id' => $sheet->id,
            'entered_by_user_id' => $user->id,
            'client_uuid' => Str::uuid()->toString(),
            'entry_number' => 1,
            'status' => TallySubmission::STATUS_DRAFT,
            'registered_voters' => 150,
            'ballots_cast' => 105,
            'valid_ballots' => 100,
            'invalid_ballots' => 3,
            'blank_ballots' => 2,
        ], $attributes));
    }

    private function createResult(
        User $user,
        TallySubmission $submission,
        ElectionOption $option,
        int $votes,
        array $attributes = []
    ): TallyResult {
        $this->actingAs($user);

        return TallyResult::query()->create(array_merge([
            'tenant_id' => $user->tenant_id,
            'tally_submission_id' => $submission->id,
            'election_option_id' => $option->id,
            'votes' => $votes,
        ], $attributes));
    }

    private function createAttachment(User $user, TallySheet $sheet, array $attributes = []): TallySheetAttachment
    {
        $this->actingAs($user);
        $uuid = Str::uuid()->toString();

        return TallySheetAttachment::query()->create(array_merge([
            'tenant_id' => $user->tenant_id,
            'tally_sheet_id' => $sheet->id,
            'uploaded_by_user_id' => $user->id,
            'client_uuid' => $uuid,
            'disk' => 'local',
            'path' => 'tallies/'.$uuid.'.jpg',
            'original_name' => 'tally-sheet.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 2048,
            'checksum_sha256' => hash('sha256', $uuid),
        ], $attributes));
    }

    private function submitEntry(User $user, TallySubmission $submission): void
    {
        $this->actingAs($user);
        $submission->update([
            'status' => TallySubmission::STATUS_SUBMITTED,
        ]);
    }

    /**
     * @return array{PollingCenter, PollingStation}
     */
    private function findGeography(int $tenantId): array
    {
        $station = PollingStation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->firstOrFail();
        $center = PollingCenter::withoutGlobalScopes()
            ->whereKey($station->polling_center_id)
            ->firstOrFail();

        return [$center, $station];
    }

    private function createUserWithRole(Tenant $tenant, string $roleSlug): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $role = Role::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $roleSlug)
            ->firstOrFail();
        $user->assignRole($role);

        return $user;
    }

    private function findUser(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function assertLogicException(string $message, Closure $callback): void
    {
        try {
            $callback();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (LogicException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
    }
}
