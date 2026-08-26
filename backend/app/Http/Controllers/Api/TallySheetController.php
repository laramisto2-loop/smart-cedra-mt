<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveTallySheetRequest;
use App\Http\Requests\RejectTallySheetRequest;
use App\Http\Requests\ReviewTallySheetRequest;
use App\Http\Requests\StoreTallySheetRequest;
use App\Http\Requests\UpdateTallySheetRequest;
use App\Http\Resources\TallySheetResource;
use App\Models\TallySheet;
use App\Models\TallySubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TallySheetController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', TallySheet::class);

        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'election_contest_id' => ['nullable', 'integer'],
            'polling_center_id' => ['nullable', 'integer'],
            'polling_station_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(TallySheet::STATUSES)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $sheets = TallySheet::query()
            ->with([
                'contest',
                'pollingCenter.area',
                'pollingStation',
                'creator',
                'reviewer',
                'approver',
            ])
            ->withCount(['submissions', 'attachments'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->input('search'));

                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('reference_code', 'like', "%{$search}%")
                        ->orWhereHas('contest', fn ($contestQuery) => $contestQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('pollingCenter', fn ($centerQuery) => $centerQuery
                            ->where('name_en', 'like', "%{$search}%")
                            ->orWhere('name_ar', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%"));
                });
            })
            ->when(
                $request->filled('election_contest_id'),
                fn ($query) => $query->where(
                    'election_contest_id',
                    $request->integer('election_contest_id')
                )
            )
            ->when(
                $request->filled('polling_center_id'),
                fn ($query) => $query->where(
                    'polling_center_id',
                    $request->integer('polling_center_id')
                )
            )
            ->when(
                $request->filled('polling_station_id'),
                fn ($query) => $query->where(
                    'polling_station_id',
                    $request->integer('polling_station_id')
                )
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->input('status'))
            )
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return TallySheetResource::collection($sheets);
    }

    public function store(StoreTallySheetRequest $request): JsonResponse
    {
        $sheet = TallySheet::query()->create([
            ...$request->validated(),
            'created_by_user_id' => $request->user()->id,
            'status' => TallySheet::STATUS_PENDING,
        ]);

        return (new TallySheetResource($this->loadSheet($sheet)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(TallySheet $tallySheet): TallySheetResource
    {
        Gate::authorize('view', $tallySheet);

        return new TallySheetResource($this->loadSheet($tallySheet));
    }

    public function update(
        UpdateTallySheetRequest $request,
        TallySheet $tallySheet
    ): TallySheetResource {
        $tallySheet->update($request->validated());

        return new TallySheetResource($this->loadSheet($tallySheet->refresh()));
    }

    public function review(
        ReviewTallySheetRequest $request,
        TallySheet $tallySheet
    ): TallySheetResource {
        $submissionId = $request->validated('submission_id')
            ?: $tallySheet->approved_submission_id;

        $tallySheet->update([
            'status' => TallySheet::STATUS_READY_FOR_REVIEW,
            'reviewed_by_user_id' => $request->user()->id,
            'reviewed_at' => now(),
            'approved_submission_id' => $submissionId,
            'reconciliation_notes' => $this->appendNote(
                $tallySheet->reconciliation_notes,
                $request->validated('notes')
            ),
        ]);

        return new TallySheetResource($this->loadSheet($tallySheet->refresh()));
    }

    public function approve(
        ApproveTallySheetRequest $request,
        TallySheet $tallySheet
    ): TallySheetResource {
        $submissionId = $request->validated('submission_id')
            ?: $tallySheet->approved_submission_id
            ?: $tallySheet->submissions()
                ->where('status', TallySubmission::STATUS_SUBMITTED)
                ->orderBy('entry_number')
                ->value('id');

        if ($submissionId === null) {
            throw ValidationException::withMessages([
                'submission_id' => 'A submitted tally entry is required for approval.',
            ]);
        }

        $tallySheet->update([
            'status' => TallySheet::STATUS_APPROVED,
            'approved_submission_id' => $submissionId,
            'approved_by_user_id' => $request->user()->id,
            'approved_at' => now(),
            'reconciliation_notes' => $this->appendNote(
                $tallySheet->reconciliation_notes,
                $request->validated('notes')
            ),
        ]);

        return new TallySheetResource($this->loadSheet($tallySheet->refresh()));
    }

    public function reject(
        RejectTallySheetRequest $request,
        TallySheet $tallySheet
    ): TallySheetResource {
        $tallySheet->update([
            'status' => TallySheet::STATUS_REJECTED,
            'approved_by_user_id' => $request->user()->id,
            'rejected_at' => now(),
            'reconciliation_notes' => $this->appendNote(
                $tallySheet->reconciliation_notes,
                'Rejected: '.$request->validated('reason')
            ),
        ]);

        return new TallySheetResource($this->loadSheet($tallySheet->refresh()));
    }

    public function destroy(TallySheet $tallySheet): never
    {
        Gate::authorize('delete', $tallySheet);

        abort(Response::HTTP_METHOD_NOT_ALLOWED);
    }

    private function loadSheet(TallySheet $sheet): TallySheet
    {
        return $sheet
            ->load([
                'contest.options',
                'pollingCenter.area',
                'pollingStation',
                'creator',
                'reviewer',
                'approver',
                'approvedSubmission.entrant',
                'approvedSubmission.results.electionOption',
                'submissions' => fn ($query) => $query
                    ->with(['entrant', 'results.electionOption'])
                    ->orderBy('entry_number'),
                'attachments' => fn ($query) => $query
                    ->with('uploader')
                    ->orderByDesc('id'),
            ])
            ->loadCount(['submissions', 'attachments']);
    }

    private function appendNote(?string $current, ?string $note): ?string
    {
        if (blank($note)) {
            return $current;
        }

        if (blank($current)) {
            return trim((string) $note);
        }

        return trim($current).PHP_EOL.trim((string) $note);
    }
}
