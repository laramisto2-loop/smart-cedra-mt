<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TallyResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tally_submission_id' => $this->tally_submission_id,
            'election_option_id' => $this->election_option_id,
            'votes' => $this->votes,
            'election_option' => new ElectionOptionResource(
                $this->whenLoaded('electionOption')
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
