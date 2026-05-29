<?php

namespace App\Services\Elections;

use App\Enums\ElectionStatus;
use App\Models\ActivityLog;
use App\Models\Election;
use App\Services\Deoris\EventPublisher;
use App\VoteSys\VoteSysPrincipal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ElectionWorkflowService
{
    public function __construct(
        private readonly EventPublisher $events,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, VoteSysPrincipal $actor): Election
    {
        return DB::transaction(function () use ($attributes, $actor) {
            $election = Election::query()->create([
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'status' => ElectionStatus::Draft->value,
                'is_active' => false,
                'starts_at' => $attributes['starts_at'] ?? null,
                'ends_at' => $attributes['ends_at'] ?? null,
                'created_by_external_id' => $actor->id,
            ]);

            $this->recordStatus($election, null, ElectionStatus::Draft, $actor->id);
            ActivityLog::record('Election created', 'blue', 'election.created', $election->id, $actor->id);
            $this->events->publish('ElectionCreated', ['election_id' => $election->id, 'name' => $election->name]);

            return $election;
        });
    }

    public function transition(Election $election, ElectionStatus $to, VoteSysPrincipal $actor, ?string $notes = null): Election
    {
        $from = $election->statusEnum();

        if (! $this->isAllowedTransition($from, $to)) {
            throw ValidationException::withMessages([
                'status' => "Cannot transition from {$from->value} to {$to->value}.",
            ]);
        }

        $election->update([
            'status' => $to->value,
            'is_active' => in_array($to, [ElectionStatus::VotingOpen, ElectionStatus::Approved], true),
            'voting_starts_at' => $to === ElectionStatus::VotingOpen ? ($election->voting_starts_at ?? now()) : $election->voting_starts_at,
            'voting_ends_at' => $to === ElectionStatus::VotingClosed ? ($election->voting_ends_at ?? now()) : $election->voting_ends_at,
            'results_released_at' => $to === ElectionStatus::Completed ? now() : $election->results_released_at,
        ]);

        $this->recordStatus($election, $from, $to, $actor->id, $notes);
        ActivityLog::record("Election status → {$to->label()}", 'gold', 'election.status', $election->id, $actor->id);

        if ($to === ElectionStatus::Completed) {
            $this->events->publish('ElectionCompleted', ['election_id' => $election->id]);
        }

        if ($to === ElectionStatus::VotingClosed) {
            $this->events->publish('ElectionClosed', ['election_id' => $election->id]);
        }

        return $election->fresh();
    }

    private function isAllowedTransition(ElectionStatus $from, ElectionStatus $to): bool
    {
        $allowed = [
            ElectionStatus::Draft->value => [ElectionStatus::CandidateRegistration, ElectionStatus::Archived],
            ElectionStatus::CandidateRegistration->value => [ElectionStatus::CandidateReview, ElectionStatus::Archived],
            ElectionStatus::CandidateReview->value => [ElectionStatus::Approved, ElectionStatus::CandidateRegistration],
            ElectionStatus::Approved->value => [ElectionStatus::VotingOpen],
            ElectionStatus::VotingOpen->value => [ElectionStatus::VotingClosed],
            ElectionStatus::VotingClosed->value => [ElectionStatus::ResultProcessing],
            ElectionStatus::ResultProcessing->value => [ElectionStatus::Completed],
            ElectionStatus::Completed->value => [ElectionStatus::Archived],
        ];

        return in_array($to, $allowed[$from->value] ?? [], true);
    }

    private function recordStatus(
        Election $election,
        ?ElectionStatus $from,
        ElectionStatus $to,
        string $actorId,
        ?string $notes = null,
    ): void {
        DB::table('election_status_history')->insert([
            'election_id' => $election->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'changed_by_external_id' => $actorId,
            'notes' => $notes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
