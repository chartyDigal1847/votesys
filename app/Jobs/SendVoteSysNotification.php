<?php

namespace App\Jobs;

use App\Models\VoteSysNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Persists a notification for a recipient and broadcasts it over the
 * voting.notifications Redis channel for real-time delivery.
 */
class SendVoteSysNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $recipientExternalId,
        public readonly string $type,
        public readonly string $title,
        public readonly ?string $body = null,
        public readonly ?array $data = null,
    ) {
        $this->onQueue(config('votesys.queues.notifications', 'votesys-notifications'));
    }

    public function handle(): void
    {
        VoteSysNotification::create([
            'recipient_external_id' => $this->recipientExternalId,
            'type'                  => $this->type,
            'title'                 => $this->title,
            'body'                  => $this->body,
            'data'                  => $this->data,
        ]);
    }
}
