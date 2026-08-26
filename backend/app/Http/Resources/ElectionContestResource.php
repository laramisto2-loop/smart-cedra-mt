<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ElectionContestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_by_user_id' => $this->created_by_user_id,
            'activated_by_user_id' => $this->activated_by_user_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'election_date' => $this->election_date?->toDateString(),
            'status' => $this->status,
            'activated_at' => $this->activated_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'options_count' => $this->whenCounted('options'),
            'tally_sheets_count' => $this->whenCounted('tallySheets'),
            'creator' => $this->whenLoaded('creator', fn () => self::user($this->creator)),
            'activator' => $this->whenLoaded('activator', fn () => self::user($this->activator)),
            'options' => ElectionOptionResource::collection($this->whenLoaded('options')),
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
