<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElectionOfficer extends Model
{
    protected $table = 'election_officers';

    protected $fillable = [
        'election_id',
        'external_id',
        'email',
        'name',
    ];

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }
}
