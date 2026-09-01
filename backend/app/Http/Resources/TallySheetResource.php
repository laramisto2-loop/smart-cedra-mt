<?php

namespace App\Http\Resources;

use App\Models\TallySheet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class TallySheetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $submissionsAreLoaded = $this->relationLoaded('submissions');
        $visibleSubmissions = $submissionsAreLoaded
            ? $this->visibleSubmissions($request)
            : collect();

        return [
            'id' => $this->id,
            'election_contest_id' => $this->election_contest_id,
            'polling_center_id' => $this->polling_center_id,
            'polling_station_id' => $this->polling_station_id,
            'created_by_user_id' => $this->created_by_user_id,
            'reviewed_by_user_id' => $this->reviewed_by_user_id,
            'approved_by_user_id' => $this->approved_by_user_id,
            'approved_submission_id' => $this->approved_submission_id,
            'reference_code' => $this->reference_code,
            'status' => $this->status,
            'notes' => $this->notes,
            'reconciliation_notes' => $this->reconciliation_notes,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),
            'rejected_at' => $this->rejected_at?->toISOString(),
            'submissions_count' => $this->whenCounted('submissions'),
            'next_entry_number' => $this->when(
                $submissionsAreLoaded,
                fn () => $this->nextEntryNumber()
            ),
            'has_hidden_submissions' => $this->when(
                $submissionsAreLoaded,
                fn () => $visibleSubmissions->count()
                    < $this->submissions->count()
            ),
            'attachments_count' => $this->whenCounted('attachments'),
            'contest' => new ElectionContestResource($this->whenLoaded('contest')),
            'polling_center' => new PollingCenterResource($this->whenLoaded('pollingCenter')),
            'polling_station' => new PollingStationResource($this->whenLoaded('pollingStation')),
            'creator' => $this->whenLoaded('creator', fn () => self::user($this->creator)),
            'reviewer' => $this->whenLoaded('reviewer', fn () => self::user($this->reviewer)),
            'approver' => $this->whenLoaded('approver', fn () => self::user($this->approver)),
            'approved_submission' => new TallySubmissionResource(
                $this->whenLoaded('approvedSubmission')
            ),
            'submissions' => $this->when(
                $submissionsAreLoaded,
                fn () => TallySubmissionResource::collection(
                    $visibleSubmissions
                )
            ),
            'attachments' => TallySheetAttachmentResource::collection($this->whenLoaded('attachments')),
            'actions' => [
                'review' => $request->user()?->can(
                    'review',
                    $this->resource
                ) ?? false,
                'approve' => $request->user()?->can(
                    'approve',
                    $this->resource
                ) ?? false,
                'reject' => $request->user()?->can(
                    'reject',
                    $this->resource
                ) ?? false,
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private static function user($user): ?array
    {
        return $user === null ? null : [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    private function visibleSubmissions(Request $request): Collection
    {
        if (
            ! in_array(
                $this->status,
                [
                    TallySheet::STATUS_PENDING,
                    TallySheet::STATUS_AWAITING_SECOND_ENTRY,
                ],
                true
            )
        ) {
            return $this->submissions->values();
        }

        $userId = $request->user()?->id;

        return $this->submissions
            ->filter(
                fn ($submission) => $userId !== null
                    && (int) $submission->entered_by_user_id
                        === (int) $userId
            )
            ->values();
    }

    private function nextEntryNumber(): ?int
    {
        $entryNumbers = $this->submissions
            ->pluck('entry_number')
            ->map(fn ($entryNumber) => (int) $entryNumber);

        foreach ([1, 2] as $entryNumber) {
            if (! $entryNumbers->contains($entryNumber)) {
                return $entryNumber;
            }
        }

        return null;
    }
}
