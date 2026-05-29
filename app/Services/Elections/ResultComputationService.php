<?php

namespace App\Services\Elections;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\ElectionResult;
use App\Models\Vote;
use App\Services\Deoris\EventPublisher;
use Illuminate\Support\Facades\DB;

class ResultComputationService
{
    public function __construct(
        private readonly EventPublisher $events,
    ) {}

    public function compute(Election $election): void
    {
        DB::transaction(function () use ($election) {
            ElectionResult::query()->where('election_id', $election->id)->delete();

            $totals = Vote::query()
                ->selectRaw('position_id, candidate_id, COUNT(*) as vote_count')
                ->where('election_id', $election->id)
                ->groupBy('position_id', 'candidate_id')
                ->get()
                ->groupBy('position_id');

            foreach ($totals as $positionId => $rows) {
                $positionTotal = $rows->sum('vote_count');

                $ranked = $rows->sortByDesc('vote_count')->values();
                foreach ($ranked as $index => $row) {
                    $pct = $positionTotal > 0
                        ? round(($row->vote_count / $positionTotal) * 100, 2)
                        : 0;

                    ElectionResult::query()->create([
                        'election_id' => $election->id,
                        'position_id' => (int) $positionId,
                        'candidate_id' => (int) $row->candidate_id,
                        'vote_count' => (int) $row->vote_count,
                        'vote_percentage' => $pct,
                        'rank' => $index + 1,
                        'computed_at' => now(),
                    ]);
                }
            }
        });

        $this->events->publish('ResultsReleased', [
            'election_id' => $election->id,
        ]);
    }

    /**
     * @return array<int, array<int, array{candidate_id: int, votes: int}>>
     */
    public function liveSummary(Election $election): array
    {
        return Vote::query()
            ->selectRaw('position_id, candidate_id, COUNT(*) as votes')
            ->where('election_id', $election->id)
            ->groupBy('position_id', 'candidate_id')
            ->get()
            ->groupBy('position_id')
            ->map(fn ($group) => $group->map(fn ($item) => [
                'candidate_id' => (int) $item->candidate_id,
                'votes' => (int) $item->votes,
            ])->values())
            ->all();
    }
}
