<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ElectionContest;
use App\Models\PollingStation;
use App\Models\TallySheet;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ResultsAnalyticsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermission('results.analytics.view'),
            Response::HTTP_FORBIDDEN
        );

        $tenantId = app(TenantContext::class)->id();
        $validated = $request->validate([
            'election_contest_id' => [
                'required',
                'integer',
                Rule::exists('election_contests', 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
            'polling_center_id' => [
                'nullable',
                'integer',
                Rule::exists('polling_centers', 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
        ]);

        $contest = ElectionContest::query()
            ->with(['options' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('ballot_order')
                ->orderBy('id')])
            ->findOrFail($validated['election_contest_id']);

        Gate::authorize('view', $contest);

        $approvedSheets = TallySheet::query()
            ->where('election_contest_id', $contest->id)
            ->where('status', TallySheet::STATUS_APPROVED)
            ->whereNotNull('approved_submission_id')
            ->when(
                isset($validated['polling_center_id']),
                fn ($query) => $query->where(
                    'polling_center_id',
                    $validated['polling_center_id']
                )
            )
            ->with([
                'pollingCenter',
                'approvedSubmission.results',
            ])
            ->get();

        $approvedSubmissions = $approvedSheets
            ->pluck('approvedSubmission')
            ->filter();

        $registeredVoters = (int) $approvedSubmissions->sum('registered_voters');
        $ballotsCast = (int) $approvedSubmissions->sum('ballots_cast');
        $validBallots = (int) $approvedSubmissions->sum('valid_ballots');
        $invalidBallots = (int) $approvedSubmissions->sum('invalid_ballots');
        $blankBallots = (int) $approvedSubmissions->sum('blank_ballots');

        $voteTotals = [];
        foreach ($approvedSubmissions as $submission) {
            foreach ($submission->results as $result) {
                $optionId = (int) $result->election_option_id;
                $voteTotals[$optionId] = ($voteTotals[$optionId] ?? 0)
                    + (int) $result->votes;
            }
        }

        $totalStations = PollingStation::query()
            ->when(
                isset($validated['polling_center_id']),
                fn ($query) => $query->where(
                    'polling_center_id',
                    $validated['polling_center_id']
                )
            )
            ->count();

        $statusBreakdown = TallySheet::query()
            ->where('election_contest_id', $contest->id)
            ->when(
                isset($validated['polling_center_id']),
                fn ($query) => $query->where(
                    'polling_center_id',
                    $validated['polling_center_id']
                )
            )
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $centerBreakdown = $approvedSheets
            ->groupBy('polling_center_id')
            ->map(function ($sheets): array {
                $submissions = $sheets->pluck('approvedSubmission')->filter();
                $registered = (int) $submissions->sum('registered_voters');
                $cast = (int) $submissions->sum('ballots_cast');
                $center = $sheets->first()->pollingCenter;

                return [
                    'polling_center_id' => $center?->id,
                    'code' => $center?->code,
                    'name_en' => $center?->name_en,
                    'name_ar' => $center?->name_ar,
                    'approved_sheets' => $sheets->count(),
                    'registered_voters' => $registered,
                    'ballots_cast' => $cast,
                    'turnout_percentage' => $this->percentage($cast, $registered),
                ];
            })
            ->values();

        return response()->json([
            'data' => [
                'contest' => [
                    'id' => $contest->id,
                    'code' => $contest->code,
                    'name' => $contest->name,
                    'status' => $contest->status,
                    'election_date' => $contest->election_date?->toDateString(),
                ],
                'filters' => [
                    'polling_center_id' => $validated['polling_center_id'] ?? null,
                ],
                'summary' => [
                    'approved_sheets' => $approvedSheets->count(),
                    'reporting_stations' => $approvedSheets
                        ->pluck('polling_station_id')
                        ->filter()
                        ->unique()
                        ->count(),
                    'total_stations' => $totalStations,
                    'reporting_percentage' => $this->percentage(
                        $approvedSheets->pluck('polling_station_id')->filter()->unique()->count(),
                        $totalStations
                    ),
                    'registered_voters' => $registeredVoters,
                    'ballots_cast' => $ballotsCast,
                    'valid_ballots' => $validBallots,
                    'invalid_ballots' => $invalidBallots,
                    'blank_ballots' => $blankBallots,
                    'turnout_percentage' => $this->percentage($ballotsCast, $registeredVoters),
                ],
                'option_totals' => $contest->options->map(fn ($option) => [
                    'election_option_id' => $option->id,
                    'code' => $option->code,
                    'name' => $option->name,
                    'option_type' => $option->option_type,
                    'ballot_order' => $option->ballot_order,
                    'votes' => $voteTotals[$option->id] ?? 0,
                    'vote_percentage' => $this->percentage(
                        $voteTotals[$option->id] ?? 0,
                        $validBallots
                    ),
                ])->values(),
                'sheet_statuses' => collect(TallySheet::STATUSES)
                    ->mapWithKeys(fn ($status) => [
                        $status => (int) ($statusBreakdown[$status] ?? 0),
                    ]),
                'center_breakdown' => $centerBreakdown,
            ],
        ]);
    }

    private function percentage(int $value, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round(($value / $total) * 100, 2);
    }
}
