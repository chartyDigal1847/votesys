<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'message',
        'type',
        'at',
        'action',
        'election_id',
        'actor_external_id',
        'subject_type',
        'subject_id',
    ];

    protected $casts = [
        'at' => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    // ── Static helper ────────────────────────────────────────────────────────

    /**
     * Record an activity log entry.
     *
     * @param  string       $message     Human-readable description.
     * @param  string       $type        Colour hint: green, blue, red, gold, gray, etc.
     * @param  string|null  $action      Machine-readable action key (e.g. 'vote.cast').
     * @param  int|null     $electionId  Related election ID, if any.
     * @param  string|null  $actorId     External ID of the actor performing the action.
     */
    public static function record(
        string $message,
        string $type = 'gray',
        ?string $action = null,
        ?int $electionId = null,
        ?string $actorId = null,
    ): void {
        static::create([
            'message'           => $message,
            'type'              => $type,
            'at'                => now(),
            'action'            => $action,
            'election_id'       => $electionId,
            'actor_external_id' => $actorId,
        ]);

        // Keep only the 200 most recent entries to prevent unbounded growth.
        $oldest = static::orderByDesc('at')->skip(200)->first();
        if ($oldest) {
            static::where('at', '<=', $oldest->at)->delete();
        }
    }
}
