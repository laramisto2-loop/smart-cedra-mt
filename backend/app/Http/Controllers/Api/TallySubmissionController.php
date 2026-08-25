<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTallySubmissionRequest;
use App\Http\Requests\SubmitTallySubmissionRequest;
use App\Http\Requests\UpdateTallySubmissionRequest;
use App\Http\Resources\TallySubmissionResource;
use App\Models\TallyResult;
use App\Models\TallySheet;
use App\Models\TallySubmission;
use App\Services\TallyReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TallySubmissionController extends Controller
{
    public function store(
        StoreTallySubmissionRequest $request,
        TallySheet $tallySheet
    ): JsonResponse {
        abort_unless(
            in_array(
                $tallySheet->status,
                [
                    TallySheet::STATUS_PENDING,
                    TallySheet::STATUS_AWAITING_SECOND_ENTRY,
                ],
                true
            ),
            Response::HTTP_CONFLICT,
            'This tally sheet no longer accepts entries.'
        );

        $clientUuid = $request->validated('client_uuid')
            ?: Str::uuid()->toString();

        $existing = TallySubmission::query()
            ->where('client_uuid', $clientUuid)
            ->first();

        if ($existing !== null) {
            if (
                (int) $existing->tally_sheet_id !== (int) $tallySheet->id
                || (int) $existing->entered_by_user_id !== (int) $request->user()->id
            ) {
                throw new HttpException(
                    Response::HTTP_CONFLICT,
                    'This submission identifier already belongs to another tally entry.'
                );
            }

            Gate::authorize('view', $existing);

            return (new TallySubmissionResource($this->loadSubmission($existing)))
                ->response();
        }

        $submission = DB::transaction(function () use (
            $request,
            $tallySheet,
            $clientUuid
        ): TallySubmission {
            $attributes = Arr::except($request->validated(), ['results', 'client_uuid']);
            $submission = TallySubmission::query()->create([
                ...$attributes,
                'tally_sheet_id' => $tallySheet->id,
                'entered_by_user_id' => $request->user()->id,
                'client_uuid' => $clientUuid,
                'status' => TallySubmission::STATUS_DRAFT,
            ]);

            $this->syncResults($submission, $request->validated('results', []));

            return $submission;
        });

        return (new TallySubmissionResource($this->loadSubmission($submission)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(TallySubmission $tallySubmission): TallySubmissionResource
    {
        Gate::authorize('view', $tallySubmission);

        return new TallySubmissionResource($this->loadSubmission($tallySubmission));
    }

    public function update(
        UpdateTallySubmissionRequest $request,
        TallySubmission $tallySubmission
    ): TallySubmissionResource {
        DB::transaction(function () use ($request, $tallySubmission): void {
            $validated = $request->validated();
            $tallySubmission->update(Arr::except($validated, 'results'));

            if (array_key_exists('results', $validated)) {
                $this->syncResults($tallySubmission, $validated['results']);
            }
        });

        return new TallySubmissionResource(
            $this->loadSubmission($tallySubmission->refresh())
        );
    }

    public function submit(
        SubmitTallySubmissionRequest $request,
        TallySubmission $tallySubmission,
        TallyReconciliationService $reconciliation
    ): TallySubmissionResource {
        DB::transaction(function () use ($tallySubmission, $reconciliation): void {
            $submission = TallySubmission::query()
                ->lockForUpdate()
                ->findOrFail($tallySubmission->id);

            $submission->update([
                'status' => TallySubmission::STATUS_SUBMITTED,
                'submitted_at' => now(),
                'received_at' => now(),
            ]);

            $sheet = TallySheet::query()
                ->lockForUpdate()
                ->findOrFail($submission->tally_sheet_id);

            $reconciliation->reconcile($sheet);
        });

        return new TallySubmissionResource(
            $this->loadSubmission($tallySubmission->refresh())
        );
    }

    public function destroy(TallySubmission $tallySubmission): Response
    {
        Gate::authorize('delete', $tallySubmission);

        DB::transaction(function () use ($tallySubmission): void {
            $tallySubmission->results->each->delete();
            $tallySubmission->delete();
        });

        return response()->noContent();
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     */
    private function syncResults(TallySubmission $submission, array $results): void
    {
        $optionIds = collect($results)
            ->pluck('election_option_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $submission->results()
            ->whereNotIn('election_option_id', $optionIds)
            ->get()
            ->each
            ->delete();

        foreach ($results as $result) {
            $existing = $submission->results()
                ->where('election_option_id', $result['election_option_id'])
                ->first();

            if ($existing === null) {
                TallyResult::query()->create([
                    'tally_submission_id' => $submission->id,
                    'election_option_id' => $result['election_option_id'],
                    'votes' => $result['votes'],
                ]);
            } else {
                $existing->update(['votes' => $result['votes']]);
            }
        }
    }

    private function loadSubmission(TallySubmission $submission): TallySubmission
    {
        return $submission->load([
            'entrant',
            'results.electionOption',
            'tallySheet.contest',
            'tallySheet.pollingCenter',
            'tallySheet.pollingStation',
        ]);
    }
}
