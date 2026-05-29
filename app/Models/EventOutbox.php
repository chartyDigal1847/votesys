<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventOutbox extends Model
{
    protected $table = 'event_outbox';

    protected $fillable = [
        'event_id',
        'event_name',
        'source_service',
        'schema_version',
        'correlation_id',
        'payload',
        'signature',
        'nonce',
        'occurred_at',
        'status',
        'attempts',
        'published_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
        'published_at' => 'datetime',
    ];
}
