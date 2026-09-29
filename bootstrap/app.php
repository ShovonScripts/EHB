<?php

use App\Http\Middleware\ApplyRedirects;
use App\Http\Middleware\CachePublicResponses;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // SECURITY.md §19 — security headers on every response (outer),
        // then legacy-path redirects (inner) so redirects carry headers.
        $middleware->append(SecurityHeaders::class);
        $middleware->append(ApplyRedirects::class);
        // Innermost: cached responses still flow back out through the headers
        // and redirect middleware above (ROADMAP Phase 8).
        $middleware->append(CachePublicResponses::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
