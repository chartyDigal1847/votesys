<?php

namespace App\Services\Deoris;

use App\Models\EventOutbox;
use Illuminate\Support\Str;

class EventPublisher
{
    public function publish(string $eventName, array $payload, ?string $correlationId = null): EventOutbox
    {
        $eventId = (string) Str::uuid();
        $nonce = Str::random(32);
        $occurredAt = now();
        $source = config('votesys.service_key', 'votesys-service');

        $envelope = [
            'event_id' => $eventId,
            'event_name' => $eventName,
            'source_service' => $source,
            'schema_version' => config('votesys.event_schema_version', '1.0'),
            'correlation_id' => $correlationId ?? (string) Str::uuid(),
            'payload' => $payload,
            'timestamp' => $occurredAt->toIso8601String(),
        ];

        $signature = $this->sign($envelope, $nonce);

        return EventOutbox::query()->create([
            'event_id' => $eventId,
            'event_name' => $eventName,
            'source_service' => $source,
            'schema_version' => config('votesys.event_schema_version', '1.0'),
            'correlation_id' => $envelope['correlation_id'],
            'payload' => $envelope,
            'signature' => $signature,
            'nonce' => $nonce,
            'occurred_at' => $occurredAt,
            'status' => 'pending',
        ]);
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    private function sign(array $envelope, string $nonce): string
    {
        $secret = (string) config('votesys.event_secret');
        $body = json_encode($envelope, JSON_THROW_ON_ERROR).$nonce;

        return hash_hmac('sha256', $body, $secret);
    }
}
