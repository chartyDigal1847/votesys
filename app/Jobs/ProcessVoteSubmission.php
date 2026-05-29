<?php

namespace App\Jobs;

use App\Services\Voting\VoteSubmissionService;
use App\VoteSys\VoteSysPrincipal;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;

/**
 * Queued wrapper for VoteSubmissionService::submit().
 * Use this when you want vote processing to happen asynchronously
 * (e.g. during high-traffic elections). For most cases the controller
 * calls VoteSubmissionService directly and this job is not needed.
 */
class ProcessVoteSubmission implements ShouldQueue
{
    use Queueable;

    public int $tries = 1; // Votes must not be retried — idempotency is handled inside the service.

    /**
     * @param  array{student_id?: string, election_id: int, selections: array<int, int|null>}  $data
     * @param  array{id: string, email: string, name: string, role: string}                    $principalArray
     * @param  string  $ip
     * @param  string  $userAgent
     */
    public function __construct(
        public readonly array $data,
        public readonly array $principalArray,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
        $this->onQueue(config('votesys.queues.voting', 'votesys-voting'));
    }

    public function handle(VoteSubmissionService $service): void
    {
        $principal = VoteSysPrincipal::fromSessionArray($this->principalArray);

        // Build a minimal Request-like object for IP/UA logging.
        $request = Request::create('/', 'POST', [], [], [], [
            'REMOTE_ADDR'     => $this->ip,
            'HTTP_USER_AGENT' => $this->userAgent,
        ]);

        $service->submit($this->data, $principal, $request);
    }
}
