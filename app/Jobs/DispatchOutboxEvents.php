<?php

namespace App\Jobs;

use App\Models\EventOutbox;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Polls the event_outbox table for pending events and delivers them to the
 * DEORIS Event Hub via HTTP POST. Runs on the votesys-events queue.
 *
 * Dispatch this job on a schedule (e.g. every minute via the scheduler) or
 * trigger it immediately after EventPublisher::publish().
 */
class DispatchOutboxEvents implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct()
    {
        $this->onQueue(config('votesys.queues.events', 'votesys-events'));
    }

    public function handle(): void
    {
        $portalUrl = rtrim((string) config('votesys.trusted_portal_url'), '/');
        $ingestUrl = $portalUrl.'/api/events/ingest';

        $pending = EventOutbox::query()
            ->where('status', 'pending')
            ->where('attempts', '<', 5)
            ->orderBy('created_at')
            ->limit(50)
            ->get();

        foreach ($pending as $outbox) {
            $outbox->increment('attempts');

            try {
                $response = Http::timeout(10)
                    ->withHeaders([
                        'X-VoteSys-Signature' => $outbox->signature,
                        'X-VoteSys-Nonce'     => $outbox->nonce,
                        'Content-Type'        => 'application/json',
                    ])
                    ->post($ingestUrl, $outbox->payload);

                if ($response->successful()) {
                    $outbox->update([
                        'status'       => 'published',
                        'published_at' => now(),
                    ]);
                } else {
                    Log::warning('[VoteSys] Event Hub rejected event', [
                        'event_id' => $outbox->event_id,
                        'status'   => $response->status(),
                        'body'     => $response->body(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('[VoteSys] Failed to dispatch outbox event', [
                    'event_id'  => $outbox->event_id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }
}
