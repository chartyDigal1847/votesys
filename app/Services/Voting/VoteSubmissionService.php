<?php

namespace App\Services\Voting;

use App\Events\VoteCast;
use App\Models\ActivityLog;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Position;
use App\Models\StudentVoter;
use App\Models\Vote;
use App\Models\VoteLog;
use App\Services\Deoris\EventPublisher;
use App\VoteSys\VoteSysPrincipal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoteSubmissionService
{
    public function __construct(
        private readonly EventPublisher $events,
    ) {}

    /**
     * @param  array{student_id?: string, election_id: int, selections: array<int, int|null>}  $data
     */
    public function submit(array $data, VoteSysPrincipal $principal, Request $request): void
    {
        $election = Election::findOrFail($data['election_id']);

        if (! $election->isVotingOpen()) {
            throw ValidationException::withMessages([
                'election_id' => 'Voting is not open for this election.',
            ]);
        }

        // Use the authenticated principal's ID as the voter identity.
        // student_id from the request is only a fallback for unauthenticated/guest calls.
        $voterId = ($principal->id !== 'guest' && $principal->id !== '')
            ? $principal->id
            : ($data['student_id'] ?? null);

        if (! $voterId || strlen($voterId) < 1) {
            throw ValidationException::withMessages([
                'student_id' => 'A valid voter identity is required.',
            ]);
        }

        $this->ensureVoterEligible($principal, $voterId);

        $positions = Position::query()
            ->with(['candidates' => fn ($q) => $q->where('status', 'approved')])
            ->where('election_id', $election->id)
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($data, $election, $positions, $voterId, $principal, $request) {
            foreach ($positions as $position) {
                $candidateId = $data['selections'][$position->id] ?? null;

                if (! $candidateId) {
                    throw ValidationException::withMessages([
                        "selections.{$position->id}" => "Please select a candidate for {$position->name}.",
                    ]);
                }

                $candidate = $position->candidates->firstWhere('id', (int) $candidateId);
                if (! $candidate) {
                    throw ValidationException::withMessages([
                        "selections.{$position->id}" => "Invalid or unapproved selection for {$position->name}.",
                    ]);
                }

                $existing = Vote::query()
                    ->where('election_id', $election->id)
                    ->where('position_id', $position->id)
                    ->where('voter_external_id', $voterId)
                    ->first();

                if ($existing?->is_locked) {
                    throw ValidationException::withMessages([
                        "selections.{$position->id}" => 'You have already voted for this position.',
                    ]);
                }

                $voteHash = $this->computeVoteHash($election->id, $position->id, (int) $candidateId, $voterId);

                $vote = Vote::updateOrCreate(
                    [
                        'election_id' => $election->id,
                        'position_id' => $position->id,
                        'student_id' => $data['student_id'] ?? $voterId,
                    ],
                    [
                        'candidate_id' => (int) $candidateId,
                        'voter_external_id' => $voterId,
                        'vote_hash' => $voteHash,
                        'is_locked' => true,
                    ]
                );

                VoteLog::query()->create([
                    'election_id' => $election->id,
                    'vote_id' => $vote->id,
                    'voter_external_id' => $voterId,
                    'position_id' => $position->id,
                    'action' => $existing ? 'vote_updated' : 'vote_cast',
                    'ip_address' => $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                    'metadata' => ['candidate_id' => (int) $candidateId],
                    'logged_at' => now(),
                ]);

                // Broadcast live vote count update over WebSocket.
                $newCount = Vote::query()
                    ->where('election_id', $election->id)
                    ->where('position_id', $position->id)
                    ->where('candidate_id', (int) $candidateId)
                    ->count();

                broadcast(new VoteCast($election->id, $position->id, (int) $candidateId, $newCount))->toOthers();
            }
        });

        ActivityLog::record(
            "Vote submitted by {$voterId}",
            'green',
            'vote.cast',
            $election->id,
            $principal->id,
        );

        $this->events->publish('VoteSubmitted', [
            'election_id' => $election->id,
            'voter_external_id' => $voterId,
        ]);
    }

    private function ensureVoterEligible(VoteSysPrincipal $principal, string $voterId): void
    {
        StudentVoter::query()->updateOrCreate(
            ['external_id' => $voterId],
            [
                'email' => $principal->email,
                'name' => $principal->name,
                'is_eligible' => true,
            ]
        );

        $voter = StudentVoter::query()->where('external_id', $voterId)->first();
        if ($voter && ! $voter->is_eligible) {
            throw ValidationException::withMessages([
                'student_id' => 'You are not eligible to vote in this election.',
            ]);
        }
    }

    private function computeVoteHash(int $electionId, int $positionId, int $candidateId, string $voterId): string
    {
        $payload = implode('|', [$electionId, $positionId, $candidateId, $voterId, now()->timestamp]);

        return hash('sha256', $payload.config('votesys.event_secret'));
    }
}
