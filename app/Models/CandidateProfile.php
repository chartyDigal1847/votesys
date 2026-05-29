<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateProfile extends Model
{
    protected $table = 'candidate_profiles';

    protected $fillable = [
        'candidate_id',
        'tagline',
        'platform',
        'campaign_links',
    ];

    protected $casts = [
        'campaign_links' => 'array',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
