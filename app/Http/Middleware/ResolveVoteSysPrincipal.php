<?php

namespace App\Http\Middleware;

use App\Enums\VoteSysRole;
use App\VoteSys\VoteSysPrincipal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveVoteSysPrincipal
{
    public function handle(Request $request, Closure $next): Response
    {
        $sessionKey = config('votesys.session_key', 'votesys_principal');
        $principal = null;

        if ($request->hasSession() && $request->session()->has($sessionKey)) {
            $stored = $request->session()->get($sessionKey);
            if (is_array($stored) && isset($stored['role'])) {
                $principal = VoteSysPrincipal::fromSessionArray($stored);
            }
        }

        $incoming = $this->principalFromHeaders($request);

        if ($incoming !== null) {
            if ($principal === null || $this->shouldRefreshPrincipal($principal, $incoming)) {
                $principal = $incoming;
                if ($request->hasSession()) {
                    $request->session()->put($sessionKey, $principal->toArray());
                }
            }
        }

        $request->attributes->set('votesys_principal', $principal ?? VoteSysPrincipal::guest());

        return $next($request);
    }

    private function principalFromHeaders(Request $request): ?VoteSysPrincipal
    {
        $roleHeader = $request->header('X-VoteSys-Role');
        $userId = $request->header('X-VoteSys-User-Id');
        $userEmail = $request->header('X-VoteSys-User-Email');
        $userName = $request->header('X-VoteSys-User-Name');

        $isDevBypass = config('votesys.allow_dev_headers')
            && app()->environment('local')
            && $request->header('X-VoteSys-Dev') === '1';

        if ($roleHeader === null && ! $isDevBypass) {
            return null;
        }

        $role = VoteSysRole::tryFromString($roleHeader ?? 'student');
        if ($role === null) {
            return null;
        }

        $allowTestHeaders = app()->environment('testing');

        if (! $isDevBypass && ! $allowTestHeaders && ($userId === null || $userEmail === null)) {
            return null;
        }

        return new VoteSysPrincipal(
            id: (string) ($userId ?? 'dev-local'),
            email: strtolower((string) ($userEmail ?? 'dev@localhost')),
            name: (string) ($userName ?? 'Local Dev'),
            role: $role,
        );
    }

    private function shouldRefreshPrincipal(VoteSysPrincipal $current, VoteSysPrincipal $incoming): bool
    {
        return $current->id !== $incoming->id
            || $current->role !== $incoming->role;
    }
}
