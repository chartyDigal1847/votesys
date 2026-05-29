<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElectionResult extends Model
{
    protected $fillable = [
        'election_id',
        'position_id',
        'candidate_id',
        'vote_count',
        'vote_percentage',
        'rank',
        'computed_at',
    ];

    protected $casts = [
        'vote_percentage' => 'decimal:2',
        'computed_at' => 'datetime',
    ];

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
