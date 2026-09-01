<?php

namespace Tests\Feature;

use App\Models\ElectionContest;
use App\Models\ElectionOption;
use App\Models\PollingCenter;
use App\Models\PollingStation;
use App\Models\Role;
use App\Models\TallySheet;
use App\Models\TallySheetAttachment;
use App\Models\TallySubmission;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\GeographySeeder;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResultsIngestionApiTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_results_api(): void
    {
        $this->getJson('/api/election-contests')->assertUnauthorized();
        $this->getJson('/api/tally-sheets')->assertUnauthorized();
        $this->getJson('/api/results/analytics?election_contest_id=1')
            ->assertUnauthorized();
    }

    public function test_user_without_results_permissions_is_forbidden(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $user = User::factory()->create([
            'tenant_id' => $admin->tenant_id,
        ]);

        $this->actingAs($user)
            ->getJson('/api/election-contests')
            ->assertForbidden();

        $this->actingAs($user)
            ->getJson('/api/tally-sheets')
            ->assertForbidden();

        $this->actingAs($user)
            ->getJson('/api/results/export?election_contest_id=1')
            ->assertForbidden();
    }

    public function test_admin_manages_contests_and_only_sees_own_tenant(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $futureAdmin = $this->findUser('admin@future.test');
        $futureContest = $this->createContest($futureAdmin, 'FUTURE-ELECTION');

        $response = $this->actingAs($admin)
            ->postJson('/api/election-contests', [
                'code' => 'CEDRA-2026',
                'name' => 'Cedra Parliamentary Election',
                'description' => 'Official Week 8 results contest.',
                'election_date' => '2026-09-01',
                'options' => [
                    [
                        'code' => 'LIST-A',
                        'name' => 'List A',
                        'option_type' => ElectionOption::TYPE_LIST,
                        'ballot_order' => 1,
                    ],
                    [
                        'code' => 'LIST-B',
                        'name' => 'List B',
                        'option_type' => ElectionOption::TYPE_LIST,
                        'ballot_order' => 2,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'CEDRA-2026')
            ->assertJsonPath('data.status', ElectionContest::STATUS_DRAFT)
            ->assertJsonCount(2, 'data.options');

        $contestId = $response->json('data.id');

        $this->actingAs($admin)
            ->patchJson("/api/election-contests/{$contestId}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', ElectionContest::STATUS_ACTIVE);

        $listResponse = $this->actingAs($admin)
            ->getJson('/api/election-contests')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $ids = collect($listResponse->json('data'))->pluck('id')->all();
        $this->assertSame([$contestId], $ids);
        $this->assertNotContains($futureContest->id, $ids);

        $this->actingAs($admin)
            ->postJson('/api/election-contests', [
                'tenant_id' => $futureAdmin->tenant_id,
                'code' => 'FORGED',
                'name' => 'Forged contest',
                'options' => [[
                    'code' => 'FORGED-A',
                    'name' => 'Forged option',
                    'option_type' => ElectionOption::TYPE_LIST,
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tenant_id');
    }

    public function test_double_entry_approval_drives_analytics_and_csv_export(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $approver = $this->createUserWithRole(
            $admin->tenant,
            'tenant_admin'
        );
        $firstAgent = $this->createUserWithRole($admin->tenant, 'field_agent');
        $secondAgent = $this->createUserWithRole($admin->tenant, 'field_agent');
        [$contest, $options] = $this->createActiveContest($admin);
        [$center, $station] = $this->findGeography($admin->tenant_id);

        $sheetResponse = $this->actingAs($firstAgent)
            ->postJson('/api/tally-sheets', [
                'election_contest_id' => $contest->id,
                'polling_center_id' => $center->id,
                'polling_station_id' => $station->id,
                'notes' => 'Primary polling-station tally.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', TallySheet::STATUS_PENDING);

        $sheetId = $sheetResponse->json('data.id');
        $firstSubmissionId = $this->createAndSubmitEntry(
            $firstAgent,
            $sheetId,
            1,
            $options,
            [70, 30]
        );

        $this->assertDatabaseHas('tally_sheets', [
            'id' => $sheetId,
            'status' => TallySheet::STATUS_AWAITING_SECOND_ENTRY,
        ]);

        $this->createAndSubmitEntry(
            $secondAgent,
            $sheetId,
            2,
            $options,
            [70, 30]
        );

        $this->assertDatabaseHas('tally_sheets', [
            'id' => $sheetId,
            'status' => TallySheet::STATUS_READY_FOR_REVIEW,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/tally-sheets/{$sheetId}/review", [
                'submission_id' => $firstSubmissionId,
                'notes' => 'Entries match and were reviewed.',
            ])
            ->assertOk()
            ->assertJsonPath('data.reviewed_by_user_id', $admin->id);

        $this->actingAs($approver)
            ->patchJson("/api/tally-sheets/{$sheetId}/approve", [
                'submission_id' => $firstSubmissionId,
                'notes' => 'Entries match and were approved.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', TallySheet::STATUS_APPROVED)
            ->assertJsonPath('data.approved_submission_id', $firstSubmissionId);

        $this->actingAs($admin)
            ->getJson("/api/results/analytics?election_contest_id={$contest->id}")
            ->assertOk()
            ->assertJsonPath('data.summary.approved_sheets', 1)
            ->assertJsonPath('data.summary.registered_voters', 150)
            ->assertJsonPath('data.summary.ballots_cast', 105)
            ->assertJsonPath('data.summary.valid_ballots', 100)
            ->assertJsonPath('data.summary.turnout_percentage', 70)
            ->assertJsonPath('data.option_totals.0.votes', 70)
            ->assertJsonPath('data.option_totals.1.votes', 30)
            ->assertJsonPath('data.sheet_statuses.approved', 1);

        $export = $this->actingAs($admin)
            ->get("/api/results/export?election_contest_id={$contest->id}")
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $export->streamedContent();
        $this->assertStringContainsString('Tally reference', $csv);
        $this->assertStringContainsString('CEDRA-RESULTS', $csv);
        $this->assertStringContainsString('LIST-A - List A', $csv);
        $this->assertStringContainsString(',70,30', str_replace("\r", '', $csv));
    }

    public function test_first_entry_is_hidden_until_independent_double_entry_finishes(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $firstAgent = $this->createUserWithRole($admin->tenant, 'field_agent');
        $secondAgent = $this->createUserWithRole($admin->tenant, 'field_agent');
        [$contest, $options] = $this->createActiveContest($admin);
        [$center, $station] = $this->findGeography($admin->tenant_id);

        $sheetId = $this->actingAs($firstAgent)
            ->postJson('/api/tally-sheets', [
                'election_contest_id' => $contest->id,
                'polling_center_id' => $center->id,
                'polling_station_id' => $station->id,
            ])
            ->assertCreated()
            ->json('data.id');

        $firstSubmissionId = $this->createAndSubmitEntry(
            $firstAgent,
            $sheetId,
            1,
            $options,
            [70, 30]
        );

        $this->actingAs($secondAgent)
            ->getJson("/api/tally-sheets/{$sheetId}")
            ->assertOk()
            ->assertJsonCount(0, 'data.submissions')
            ->assertJsonPath('data.submissions_count', 1)
            ->assertJsonPath('data.next_entry_number', 2)
            ->assertJsonPath('data.has_hidden_submissions', true);

        $this->actingAs($admin)
            ->getJson("/api/tally-sheets/{$sheetId}")
            ->assertOk()
            ->assertJsonCount(0, 'data.submissions');

        $this->actingAs($secondAgent)
            ->getJson("/api/tally-submissions/{$firstSubmissionId}")
            ->assertForbidden();

        $this->actingAs($firstAgent)
            ->getJson("/api/tally-sheets/{$sheetId}")
            ->assertOk()
            ->assertJsonCount(1, 'data.submissions')
            ->assertJsonPath(
                'data.submissions.0.id',
                $firstSubmissionId
            );

        $this->createAndSubmitEntry(
            $secondAgent,
            $sheetId,
            2,
            $options,
            [70, 30]
        );

        $this->actingAs($admin)
            ->getJson("/api/tally-sheets/{$sheetId}")
            ->assertOk()
            ->assertJsonCount(2, 'data.submissions')
            ->assertJsonPath('data.has_hidden_submissions', false);
    }

    public function test_tally_review_and_approval_enforce_maker_checker_separation(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $secondAgent = $this->createUserWithRole($admin->tenant, 'field_agent');
        $reviewer = $this->createUserWithRole($admin->tenant, 'tenant_admin');
        $approver = $this->createUserWithRole($admin->tenant, 'tenant_admin');
        [$contest, $options] = $this->createActiveContest($admin);
        [$center, $station] = $this->findGeography($admin->tenant_id);

        $sheetId = $this->actingAs($admin)
            ->postJson('/api/tally-sheets', [
                'election_contest_id' => $contest->id,
                'polling_center_id' => $center->id,
                'polling_station_id' => $station->id,
            ])
            ->assertCreated()
            ->json('data.id');

        $firstSubmissionId = $this->createAndSubmitEntry(
            $admin,
            $sheetId,
            1,
            $options,
            [70, 30]
        );
        $this->createAndSubmitEntry(
            $secondAgent,
            $sheetId,
            2,
            $options,
            [70, 30]
        );

        $this->actingAs($approver)
            ->patchJson("/api/tally-sheets/{$sheetId}/approve", [
                'submission_id' => $firstSubmissionId,
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patchJson("/api/tally-sheets/{$sheetId}/review", [
                'submission_id' => $firstSubmissionId,
            ])
            ->assertForbidden();

        $this->actingAs($reviewer)
            ->patchJson("/api/tally-sheets/{$sheetId}/review", [
                'submission_id' => $firstSubmissionId,
                'notes' => 'Independent administrative review.',
            ])
            ->assertOk()
            ->assertJsonPath('data.reviewed_by_user_id', $reviewer->id);

        $this->actingAs($reviewer)
            ->patchJson("/api/tally-sheets/{$sheetId}/approve", [
                'submission_id' => $firstSubmissionId,
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patchJson("/api/tally-sheets/{$sheetId}/approve", [
                'submission_id' => $firstSubmissionId,
            ])
            ->assertForbidden();

        $this->actingAs($approver)
            ->patchJson("/api/tally-sheets/{$sheetId}/approve", [
                'submission_id' => $firstSubmissionId,
                'notes' => 'Independent final approval.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', TallySheet::STATUS_APPROVED)
            ->assertJsonPath('data.approved_by_user_id', $approver->id);
    }

    public function test_closed_contest_cannot_be_updated(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        [$contest] = $this->createActiveContest($admin);
        $originalName = $contest->name;

        $this->actingAs($admin)
            ->patchJson("/api/election-contests/{$contest->id}/close")
            ->assertOk()
            ->assertJsonPath('data.status', ElectionContest::STATUS_CLOSED);

        $this->actingAs($admin)
            ->patchJson("/api/election-contests/{$contest->id}", [
                'name' => 'Improper closed-contest edit',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('election_contests', [
            'id' => $contest->id,
            'name' => $originalName,
            'status' => ElectionContest::STATUS_CLOSED,
        ]);
    }

    public function test_discrepant_entries_require_an_explicit_review_selection(): void
    {
        $admin = $this->findUser('admin@cedra.test');
        $firstAgent = $this->createUserWithRole($admin->tenant, 'field_agent');
        $secondAgent = $this->createUserWithRole($admin->tenant, 'field_agent');
        [$contest, $options] = $this->createActiveContest($admin);
        [$center, $station] = $this->findGeography($admin->tenant_id);

        $sheetId = $this->actingAs($firstAgent)
            ->postJson('/api/tally-sheets', [
                'election_contest_id' => $contest->id,
                'polling_center_id' => $center->id,
                'polling_station_id' => $station->id,
            ])
            ->assertCreated()
            ->json('data.id');

        $firstSubmissionId = $this->createAndSubmitEntry(
            $firstAgent,
            $sheetId,
            1,
            $options,
            [70, 30]
        );
        $this->createAndSubmitEntry(
            $secondAgent,
            $sheetId,
            2,
            $options,
            [69, 31]
        );

        $this->assertDatabaseHas('tally_sheets', [
            'id' => $sheetId,
            'status' => TallySheet::STATUS_DISCREPANCY,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/tally-sheets/{$sheetId}/review", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('submission_id');

        $this->actingAs($admin)
            ->patchJson("/api/tally-sheets/{$sheetId}/review", [
                'submission_id' => $firstSubmissionId,
                'notes' => 'Paper evidence confirms the first entry.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', TallySheet::STATUS_READY_FOR_REVIEW)
            ->assertJsonPath('data.approved_submission_id', $firstSubmissionId);
    }

    public function test_private_tally_evidence_is_idempotent_downloadable_and_deletable(): void
    {
        Storage::fake('local');

        $admin = $this->findUser('admin@cedra.test');
        $agent = $this->createUserWithRole($admin->tenant, 'field_agent');
        [$contest] = $this->createActiveContest($admin);
        [$center, $station] = $this->findGeography($admin->tenant_id);

        $sheetId = $this->actingAs($agent)
            ->postJson('/api/tally-sheets', [
                'election_contest_id' => $contest->id,
                'polling_center_id' => $center->id,
                'polling_station_id' => $station->id,
            ])
            ->assertCreated()
            ->json('data.id');

        $clientUuid = Str::uuid()->toString();
        $response = $this->actingAs($agent)
            ->postJson("/api/tally-sheets/{$sheetId}/attachments", [
                'client_uuid' => $clientUuid,
                'file' => UploadedFile::fake()->create(
                    'station-tally.pdf',
                    100,
                    'application/pdf'
                ),
            ])
            ->assertCreated()
            ->assertJsonPath('data.original_name', 'station-tally.pdf');

        $attachmentId = $response->json('data.id');
        $payload = $response->json('data');
        $this->assertArrayNotHasKey('disk', $payload);
        $this->assertArrayNotHasKey('path', $payload);
        $this->assertArrayNotHasKey('checksum_sha256', $payload);

        $attachment = TallySheetAttachment::query()->findOrFail($attachmentId);
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs($agent)
            ->postJson("/api/tally-sheets/{$sheetId}/attachments", [
                'client_uuid' => $clientUuid,
                'file' => UploadedFile::fake()->create(
                    'duplicate.pdf',
                    100,
                    'application/pdf'
                ),
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $attachmentId);

        $this->assertDatabaseCount('tally_sheet_attachments', 1);

        $this->actingAs($agent)
            ->get("/api/tally-sheet-attachments/{$attachmentId}/download")
            ->assertOk()
            ->assertDownload('station-tally.pdf');

        $this->actingAs($agent)
            ->deleteJson("/api/tally-sheet-attachments/{$attachmentId}")
            ->assertNoContent();

        Storage::disk('local')->assertMissing($attachment->path);
        $this->assertDatabaseMissing('tally_sheet_attachments', [
            'id' => $attachmentId,
        ]);
    }

    /**
     * @return array{ElectionContest, Collection<int, ElectionOption>}
     */
    private function createActiveContest(User $admin): array
    {
        $contest = $this->createContest($admin, 'CEDRA-RESULTS');

        $this->actingAs($admin);
        $options = collect([
            ['code' => 'LIST-A', 'name' => 'List A', 'ballot_order' => 1],
            ['code' => 'LIST-B', 'name' => 'List B', 'ballot_order' => 2],
        ])->map(fn (array $attributes) => ElectionOption::query()->create([
            'election_contest_id' => $contest->id,
            'option_type' => ElectionOption::TYPE_LIST,
            'is_active' => true,
            ...$attributes,
        ]));

        return [$contest, $options];
    }

    private function createContest(User $admin, string $code): ElectionContest
    {
        $this->actingAs($admin);

        return ElectionContest::query()->create([
            'created_by_user_id' => $admin->id,
            'activated_by_user_id' => $admin->id,
            'code' => $code,
            'name' => "{$code} contest",
            'status' => ElectionContest::STATUS_ACTIVE,
            'election_date' => '2026-09-01',
        ]);
    }

    /**
     * @param  Collection<int, ElectionOption>  $options
     * @param  array{int, int}  $votes
     */
    private function createAndSubmitEntry(
        User $agent,
        int $sheetId,
        int $entryNumber,
        Collection $options,
        array $votes
    ): int {
        $response = $this->actingAs($agent)
            ->postJson("/api/tally-sheets/{$sheetId}/submissions", [
                'client_uuid' => Str::uuid()->toString(),
                'entry_number' => $entryNumber,
                'registered_voters' => 150,
                'ballots_cast' => 105,
                'valid_ballots' => 100,
                'invalid_ballots' => 3,
                'blank_ballots' => 2,
                'results' => $options->values()->map(
                    fn (ElectionOption $option, int $index) => [
                        'election_option_id' => $option->id,
                        'votes' => $votes[$index],
                    ]
                )->all(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.entry_number', $entryNumber)
            ->assertJsonPath('data.status', TallySubmission::STATUS_DRAFT);

        $submissionId = $response->json('data.id');

        $this->actingAs($agent)
            ->patchJson("/api/tally-submissions/{$submissionId}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', TallySubmission::STATUS_SUBMITTED);

        return $submissionId;
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
}
