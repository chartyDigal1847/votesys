<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoteLog extends Model
{
    protected $fillable = [
        'election_id',
        'vote_id',
        'voter_external_id',
        'position_id',
        'action',
        'ip_address',
        'user_agent',
        'metadata',
        'logged_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'logged_at' => 'datetime',
    ];

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function vote(): BelongsTo
    {
        return $this->belongsTo(Vote::class);
    }
}
