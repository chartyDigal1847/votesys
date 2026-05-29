<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast after a vote is successfully recorded.
 * Frontend subscribes to the public channel "election.{id}" to receive
 * live vote count updates without polling.
 */
class VoteCast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $electionId,
        public readonly int $positionId,
        public readonly int $candidateId,
        public readonly int $newVoteCount,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('election.'.$this->electionId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'vote.cast';
    }

    public function broadcastWith(): array
    {
        return [
            'election_id'    => $this->electionId,
            'position_id'    => $this->positionId,
            'candidate_id'   => $this->candidateId,
            'new_vote_count' => $this->newVoteCount,
        ];
    }
}
