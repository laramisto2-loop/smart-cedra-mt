<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ElectionContest;
use App\Models\TallySheet;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResultsExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        abort_unless(
            $request->user()->hasPermission('results.exports.create'),
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

        $sheets = TallySheet::query()
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
                'pollingStation',
                'approvedSubmission.results',
            ])
            ->orderBy('polling_center_id')
            ->orderBy('polling_station_id')
            ->get();

        $filename = sprintf(
            'results-%s-%s.csv',
            strtolower((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $contest->code)),
            now()->format('Ymd-His')
        );

        return response()->streamDownload(
            function () use ($contest, $sheets): void {
                $stream = fopen('php://output', 'wb');

                if ($stream === false) {
                    return;
                }

                fwrite($stream, "\xEF\xBB\xBF");
                fwrite($stream, "sep=,\r\n");

                fputcsv($stream, [
                    'Tally reference',
                    'Contest code',
                    'Contest name',
                    'Polling center code',
                    'Polling center name',
                    'Station number',
                    'Station name',
                    'Registered voters',
                    'Ballots cast',
                    'Valid ballots',
                    'Invalid ballots',
                    'Blank ballots',
                    'Turnout percentage',
                    'Approved at',
                    ...$contest->options->map(
                        fn ($option) => "{$option->code} - {$option->name}"
                    )->all(),
                ]);

                foreach ($sheets as $sheet) {
                    $submission = $sheet->approvedSubmission;

                    if ($submission === null) {
                        continue;
                    }

                    $votes = $submission->results
                        ->keyBy('election_option_id');

                    $row = [
                        $sheet->reference_code,
                        $contest->code,
                        $contest->name,
                        $sheet->pollingCenter?->code,
                        $sheet->pollingCenter?->name_en,
                        $sheet->pollingStation?->station_number,
                        $sheet->pollingStation?->name_en,
                        $submission->registered_voters,
                        $submission->ballots_cast,
                        $submission->valid_ballots,
                        $submission->invalid_ballots,
                        $submission->blank_ballots,
                        $this->percentage(
                            (int) $submission->ballots_cast,
                            (int) $submission->registered_voters
                        ),
                        $sheet->approved_at?->toIso8601String(),
                    ];

                    foreach ($contest->options as $option) {
                        $row[] = (int) ($votes->get($option->id)?->votes ?? 0);
                    }

                    fputcsv(
                        $stream,
                        array_map([$this, 'safeCsvValue'], $row)
                    );
                }

                fclose($stream);
            },
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store, private',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function percentage(int $value, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round(($value / $total) * 100, 2);
    }

    private function safeCsvValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

        if (preg_match('/^[=+\-@]/', ltrim($value)) === 1) {
            return "'{$value}";
        }

        return $value;
    }
}
