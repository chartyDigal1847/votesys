<?php

namespace App\Jobs;

use App\Models\Election;
use App\Services\Elections\ResultComputationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Asynchronously computes and stores election results.
 * Dispatched when an election transitions to result_processing.
 */
class ComputeElectionResults implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $electionId,
    ) {
        $this->onQueue(config('votesys.queues.analytics', 'votesys-analytics'));
    }

    public function handle(ResultComputationService $service): void
    {
        $election = Election::find($this->electionId);

        if (! $election) {
            Log::warning('[VoteSys] ComputeElectionResults: election not found', ['id' => $this->electionId]);
            return;
        }

        $service->compute($election);

        Log::info('[VoteSys] Election results computed', ['election_id' => $this->electionId]);
    }
}
