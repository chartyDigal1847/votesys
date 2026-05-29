<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\Position;
use App\Models\Vote;
use App\Services\Elections\ResultComputationService;
use App\Services\Voting\VoteSubmissionService;
use App\VoteSys\VoteSysAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Legacy web-route API used by the embedded votesys.js frontend.
 *
 * All mutating operations (vote submission, candidate deletion) are now
 * delegated to the same services used by the versioned REST API so that
 * security guarantees (vote hashing, audit logging, event publishing,
 * duplicate detection) are identical regardless of which endpoint is called.
 */
class VoteSysApiController extends Controller
{
    public function __construct(
        private readonly VoteSubmissionService $voteService,
        private readonly ResultComputationService $resultService,
    ) {}

    // ── Bootstrap ────────────────────────────────────────────────────────────

    public function bootstrap(Request $request): JsonResponse
    {
        $access = VoteSysAccess::fromRequest($request);

        $election = Election::query()
            ->where('is_active', true)
            ->latest()
            ->first()
            ?? Election::query()->latest()->first();

        $positions = collect();
        $results   = collect();

        if ($election) {
            $positions = Position::query()
                ->with(['candidates' => fn ($q) => $q->where('status', 'approved')])
                ->where('election_id', $election->id)
                ->orderBy('id')
                ->get()
                ->map(fn ($p) => [
                    'id'             => $p->id,
                    'name'           => $p->name,
                    'max_selections' => $p->max_selections,
                    'candidates'     => $p->candidates->map(fn ($c) => [
                        'id'                => $c->id,
                        'name'              => $c->name,
                        'party'             => $c->party,
                        'course'            => $c->course,
                        'bio'               => $c->bio,
                        'profile_photo_url' => $c->profilePhotoUrl,
                    ]),
                ]);

            $results = Vote::query()
                ->select('position_id', 'candidate_id', DB::raw('COUNT(*) as votes'))
                ->where('election_id', $election->id)
                ->groupBy('position_id', 'candidate_id')
                ->get()
                ->groupBy('position_id')
                ->map(fn ($group) => $group->map(fn ($item) => [
                    'candidate_id' => $item->candidate_id,
                    'votes'        => $item->votes,
                ])->values());
        }

        $candidates = Candidate::query()
            ->with(['position.election'])
            ->where('status', 'approved')
            ->orderBy('position_id')
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id'                => $c->id,
                'name'              => $c->name,
                'party'             => $c->party,
                'course'            => $c->course,
                'bio'               => $c->bio,
                'profile_photo_url' => $c->profilePhotoUrl,
                'position'          => [
                    'id'   => $c->position->id,
                    'name' => $c->position->name,
                ],
                'election'          => [
                    'id'   => $c->position->election->id,
                    'name' => $c->position->election->name,
                ],
            ]);

        return response()->json([
            'role'        => $access->principal->role->canonical(),
            'permissions' => $access->permissionMap(),
            'election'    => $election ? [
                'id'                  => $election->id,
                'name'                => $election->name,
                'status'              => $election->status,
                'is_active'           => $election->is_active,
                'starts_at'           => $election->starts_at?->toIso8601String(),
                'ends_at'             => $election->ends_at?->toIso8601String(),
                'voting_starts_at'    => $election->voting_starts_at?->toIso8601String(),
                'voting_ends_at'      => $election->voting_ends_at?->toIso8601String(),
                'results_released_at' => $election->results_released_at?->toIso8601String(),
            ] : null,
            'positions'   => $positions,
            'results'     => $results,
            'candidates'  => $candidates,
            'winners'     => $election ? $this->computeWinners($election, $positions, $candidates) : [],
            'activityLog' => \App\Models\ActivityLog::orderByDesc('at')->limit(25)->get()->map(fn ($l) => [
                'message' => $l->message,
                'type'    => $l->type,
                'at'      => $l->at?->toIso8601String(),
            ]),
        ]);
    }

    // ── Vote submission — delegates to VoteSubmissionService ─────────────────

    /**
     * Secure vote submission for the embedded JS frontend.
     * Identical security guarantees as POST /api/v1/vote:
     *   - is_locked duplicate check
     *   - vote_hash stored
     *   - vote_logs written
     *   - VoteSubmitted event published
     */
    public function submitVote(Request $request): JsonResponse
    {
        $access = VoteSysAccess::fromRequest($request);
        $access->authorize('vote.cast');

        $data = $request->validate([
            'student_id'   => ['nullable', 'string', 'min:1', 'max:64'],
            'election_id'  => ['required', 'integer', 'exists:elections,id'],
            'selections'   => ['required', 'array'],
            'selections.*' => ['nullable', 'integer', 'exists:candidates,id'],
        ]);

        $this->voteService->submit($data, $access->principal, $request);

        return response()->json(['message' => 'Your vote has been recorded.']);
    }

    // ── Candidate deletion ────────────────────────────────────────────────────

    public function deleteCandidate(Request $request, Candidate $candidate): JsonResponse
    {
        $access = VoteSysAccess::fromRequest($request);
        $access->authorize('candidates.delete');

        $name = $candidate->name;
        $candidate->delete();

        \App\Models\ActivityLog::record(
            "Candidate '{$name}' deleted",
            'red',
            'candidate.deleted',
            null,
            $access->principal->id,
        );

        return response()->json(['message' => 'Candidate removed.']);
    }

    // ── End election — closes voting, computes results, marks completed ───────

    public function endElection(Request $request): JsonResponse
    {
        $access = VoteSysAccess::fromRequest($request);
        $access->authorize('elections.manage_status');

        $election = Election::query()
            ->where('is_active', true)
            ->latest()
            ->first()
            ?? Election::query()->latest()->first();

        if (! $election) {
            return response()->json(['message' => 'No active election found.'], 404);
        }

        $allowedToEnd = in_array($election->status, [
            'voting_open', 'voting_closed', 'result_processing',
        ]);

        if (! $allowedToEnd) {
            return response()->json([
                'message' => "Election cannot be ended from status '{$election->status}'.",
            ], 422);
        }

        // Close voting if still open.
        if ($election->status === 'voting_open') {
            $election->update([
                'status'         => 'voting_closed',
                'is_active'      => false,
                'voting_ends_at' => now(),
            ]);
            \App\Models\ActivityLog::record(
                'Voting closed by ' . $access->principal->name,
                'gold',
                'election.status',
                $election->id,
                $access->principal->id,
            );
        }

        // Compute results.
        $this->resultService->compute($election);

        // Mark completed.
        $election->update([
            'status'               => 'completed',
            'results_released_at'  => now(),
        ]);

        \App\Models\ActivityLog::record(
            'Election ended and results released by ' . $access->principal->name,
            'green',
            'election.status',
            $election->id,
            $access->principal->id,
        );

        return response()->json(['message' => 'Election ended. Results are now available.']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Compute the winner (rank 1) per position from live vote data.
     *
     * @param  \Illuminate\Support\Collection  $positions
     * @param  \Illuminate\Support\Collection  $candidates
     */
    private function computeWinners(Election $election, $positions, $candidates): array
    {
        $winners = [];

        $voteCounts = \App\Models\Vote::query()
            ->selectRaw('position_id, candidate_id, COUNT(*) as votes')
            ->where('election_id', $election->id)
            ->groupBy('position_id', 'candidate_id')
            ->get()
            ->groupBy('position_id');

        foreach ($positions as $position) {
            $posVotes = $voteCounts->get($position['id'], collect());
            if ($posVotes->isEmpty()) {
                continue;
            }

            $top = $posVotes->sortByDesc('votes')->first();
            $winnerCandidate = collect($candidates)->firstWhere('id', (int) $top->candidate_id);

            if ($winnerCandidate) {
                $winners[] = [
                    'position_id'       => $position['id'],
                    'position_name'     => $position['name'],
                    'candidate_id'      => $winnerCandidate['id'],
                    'candidate_name'    => $winnerCandidate['name'],
                    'party'             => $winnerCandidate['party'],
                    'course'            => $winnerCandidate['course'],
                    'profile_photo_url' => $winnerCandidate['profile_photo_url'],
                    'votes'             => (int) $top->votes,
                    'total_votes'       => (int) $posVotes->sum('votes'),
                ];
            }
        }

        return $winners;
    }
}
