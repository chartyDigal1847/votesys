<?php

namespace App\Http\Middleware;

use App\VoteSys\VoteSysAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVoteSysPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        VoteSysAccess::fromRequest($request)->authorize($permission);

        return $next($request);
    }
}
