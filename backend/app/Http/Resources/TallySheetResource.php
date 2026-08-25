<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TallySheetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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
            'submissions' => TallySubmissionResource::collection($this->whenLoaded('submissions')),
            'attachments' => TallySheetAttachmentResource::collection($this->whenLoaded('attachments')),
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
}
