<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TallySubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tally_sheet_id' => $this->tally_sheet_id,
            'entered_by_user_id' => $this->entered_by_user_id,
            'client_uuid' => $this->client_uuid,
            'reference_code' => $this->reference_code,
            'entry_number' => $this->entry_number,
            'status' => $this->status,
            'registered_voters' => $this->registered_voters,
            'ballots_cast' => $this->ballots_cast,
            'valid_ballots' => $this->valid_ballots,
            'invalid_ballots' => $this->invalid_ballots,
            'blank_ballots' => $this->blank_ballots,
            'notes' => $this->notes,
            'entered_at' => $this->entered_at?->toISOString(),
            'submitted_at' => $this->submitted_at?->toISOString(),
            'received_at' => $this->received_at?->toISOString(),
            'entrant' => $this->whenLoaded('entrant', fn () => $this->entrant === null ? null : [
                'id' => $this->entrant->id,
                'name' => $this->entrant->name,
                'email' => $this->entrant->email,
            ]),
            'results' => TallyResultResource::collection($this->whenLoaded('results')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
