<?php

use App\Http\Middleware\EnsureVoteSysPermission;
use App\Http\Middleware\ResolveVoteSysPrincipal;
use App\Http\Middleware\VoteSysCspMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(VoteSysCspMiddleware::class);
        $middleware->validateCsrfTokens(except: [
            'sso/exchange',
            'votesys/api/*',
        ]);
        $middleware->web(append: [
            ResolveVoteSysPrincipal::class,
        ]);
        $middleware->alias([
            'votesys.principal' => ResolveVoteSysPrincipal::class,
            'votesys.permission' => EnsureVoteSysPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
