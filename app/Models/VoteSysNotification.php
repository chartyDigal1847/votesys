<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoteSysNotification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'recipient_external_id',
        'type',
        'title',
        'body',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];
}
