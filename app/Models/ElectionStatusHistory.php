<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElectionStatusHistory extends Model
{
    protected $table = 'election_status_history';

    protected $fillable = [
        'election_id',
        'from_status',
        'to_status',
        'changed_by_external_id',
        'notes',
    ];

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }
}
