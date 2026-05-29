<?php

namespace App\Models;

use App\Enums\ElectionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Election extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'status',
        'is_active',
        'starts_at',
        'ends_at',
        'voting_starts_at',
        'voting_ends_at',
        'results_released_at',
        'created_by_external_id',
    ];

    protected $casts = [
        'is_active'           => 'boolean',
        'starts_at'           => 'datetime',
        'ends_at'             => 'datetime',
        'voting_starts_at'    => 'datetime',
        'voting_ends_at'      => 'datetime',
        'results_released_at' => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(ElectionResult::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ElectionStatusHistory::class);
    }

    public function officers(): HasMany
    {
        return $this->hasMany(ElectionOfficer::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    // ── Status helpers ───────────────────────────────────────────────────────

    public function statusEnum(): ElectionStatus
    {
        return ElectionStatus::from($this->status ?? ElectionStatus::Draft->value);
    }

    public function isVotingOpen(): bool
    {
        return $this->statusEnum() === ElectionStatus::VotingOpen;
    }

    public function isCompleted(): bool
    {
        return $this->statusEnum() === ElectionStatus::Completed;
    }

    public function isArchived(): bool
    {
        return $this->statusEnum() === ElectionStatus::Archived;
    }

    public function canRegisterCandidates(): bool
    {
        return $this->statusEnum()->canRegisterCandidates();
    }
}
