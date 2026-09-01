<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallAssignment;
use App\Models\CallAttempt;
use App\Models\CampaignTask;
use App\Models\Contact;
use App\Models\Incident;
use App\Models\OutboundMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardSummaryController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => [
                'generated_at' => now()->toIso8601String(),
                'contacts' => $user->hasPermission('contacts.view')
                    ? $this->contactSummary()
                    : null,
                'tasks' => $user->hasPermission('tasks.view')
                    ? $this->taskSummary($user)
                    : null,
                'incidents' => $user->hasPermission('incidents.view')
                    ? $this->incidentSummary($user)
                    : null,
                'messages' => $user->hasPermission('messages.view')
                    ? $this->messageSummary()
                    : null,
                'calls' => $user->hasPermission(
                    'calls.assignments.view'
                )
                    ? $this->callSummary($user)
                    : null,
            ],
        ]);
    }

    private function contactSummary(): array
    {
        $total = Contact::query()->count();
        $active = Contact::query()
            ->where('status', 'active')
            ->count();
        $consented = Contact::query()
            ->where('status', 'active')
            ->whereHas(
                'consents',
                fn (Builder $query) => $query->where(
                    'status',
                    'granted'
                )
            )
            ->count();

        return [
            'total' => $total,
            'active' => $active,
            'with_granted_consent' => $consented,
            'consent_coverage_rate' => $this->rate(
                $consented,
                $active
            ),
        ];
    }

    private function taskSummary(User $user): array
    {
        $query = CampaignTask::query()->when(
            ! $user->hasPermission('tasks.update'),
            fn (Builder $scope) => $scope->where(
                'assigned_to_user_id',
                $user->id
            )
        );
        $counts = $this->statusCounts(
            $query,
            CampaignTask::STATUSES
        );
        $eligible = array_sum($counts) - $counts['cancelled'];

        return [
            'total' => array_sum($counts),
            'open' => $counts['pending'] + $counts['in_progress'],
            'completion_rate' => $this->rate(
                $counts['completed'],
                $eligible
            ),
            'by_status' => $counts,
        ];
    }

    private function incidentSummary(User $user): array
    {
        $query = Incident::query()->when(
            ! $user->hasPermission('incidents.review'),
            fn (Builder $scope) => $scope->where(
                fn (Builder $accessible) => $accessible
                    ->where('reported_by_user_id', $user->id)
                    ->orWhere('assigned_to_user_id', $user->id)
            )
        );
        $counts = $this->statusCounts(
            $query,
            Incident::STATUSES
        );
        $openStatuses = ['submitted', 'in_review'];
        $open = $counts['submitted'] + $counts['in_review'];
        $criticalOpen = (clone $query)
            ->where('severity', 'critical')
            ->whereIn('status', $openStatuses)
            ->count();

        return [
            'total' => array_sum($counts),
            'open' => $open,
            'critical_open' => $criticalOpen,
            'closed_rate' => $this->rate(
                $counts['resolved'] + $counts['dismissed'],
                array_sum($counts)
            ),
            'by_status' => $counts,
        ];
    }

    private function messageSummary(): array
    {
        $counts = $this->statusCounts(
            OutboundMessage::query(),
            OutboundMessage::STATUSES
        );
        $delivered = $counts['delivered'] + $counts['read'];
        $finalDeliveryOutcomes = $delivered + $counts['failed'];

        return [
            'total' => array_sum($counts),
            'delivered' => $delivered,
            'failed' => $counts['failed'],
            'delivery_rate' => $this->rate(
                $delivered,
                $finalDeliveryOutcomes
            ),
            'by_status' => $counts,
        ];
    }

    private function callSummary(User $user): array
    {
        $query = CallAssignment::query()->when(
            ! $this->mayManageAssignments($user),
            fn (Builder $scope) => $scope->where(
                fn (Builder $accessible) => $accessible
                    ->where('assigned_to_user_id', $user->id)
                    ->orWhere(
                        fn (Builder $unassigned) => $unassigned
                            ->whereNull('assigned_to_user_id')
                            ->where('status', 'pending')
                    )
            )
        );
        $counts = $this->statusCounts(
            $query,
            CallAssignment::STATUSES
        );
        $finished = $counts['completed']
            + $counts['skipped']
            + $counts['cancelled'];
        $attempts = $user->hasPermission('calls.attempts.view')
            ? CallAttempt::query()
                ->whereHas(
                    'callAssignment',
                    fn (Builder $scope) => $scope->whereIn(
                        'id',
                        (clone $query)->select('id')
                    )
                )
                ->count()
            : null;

        return [
            'total' => array_sum($counts),
            'open' => $counts['pending'] + $counts['in_progress'],
            'attempts' => $attempts,
            'completion_rate' => $this->rate(
                $counts['completed'],
                $finished
            ),
            'by_status' => $counts,
        ];
    }

    private function statusCounts(
        Builder $query,
        array $statuses
    ): array {
        return collect($statuses)
            ->mapWithKeys(
                fn (string $status) => [
                    $status => (clone $query)
                        ->where('status', $status)
                        ->count(),
                ]
            )
            ->all();
    }

    private function rate(int $numerator, int $denominator): float
    {
        if ($denominator === 0) {
            return 0.0;
        }

        return round(($numerator / $denominator) * 100, 1);
    }

    private function mayManageAssignments(User $user): bool
    {
        return $user->hasRole('tenant_admin')
            || $user->hasRole('coordinator');
    }
}
