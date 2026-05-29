<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * VoteSys uses in-memory PORTAL_USER from module-bridge.js — no local users table.
 */
class PortalAccount extends Authenticatable
{
    protected $table = 'sessions';
}
