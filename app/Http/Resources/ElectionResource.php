<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Election */
class ElectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => $this->statusEnum()->label(),
            'is_active' => $this->is_active,
            'is_voting_open' => $this->isVotingOpen(),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'voting_starts_at' => $this->voting_starts_at?->toIso8601String(),
            'voting_ends_at' => $this->voting_ends_at?->toIso8601String(),
            'results_released_at' => $this->results_released_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
