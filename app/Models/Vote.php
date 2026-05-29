<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vote extends Model
{
    protected $fillable = [
        'election_id',
        'position_id',
        'candidate_id',
        'student_id',
        'voter_external_id',
        'vote_hash',
        'is_locked',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
