<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentVoter extends Model
{
    protected $fillable = [
        'external_id',
        'email',
        'name',
        'course',
        'is_eligible',
    ];

    protected $casts = [
        'is_eligible' => 'boolean',
    ];
}
