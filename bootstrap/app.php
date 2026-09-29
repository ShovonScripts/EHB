<?php

use App\Http\Middleware\ApplyRedirects;
use App\Http\Middleware\CachePublicResponses;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Filesystem\ServeFile;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            $disk = config('filesystems.disks.public');

            if (! is_array($disk) || ($disk['driver'] ?? null) !== 'local' || ($disk['serve'] ?? false) !== true) {
                return;
            }

            // The same file-serving handler Laravel registers per serveable
            // disk, registered at the path the *application* sees rather than
            // the path the browser asks for.
            //
            // Laravel registers its route at the path the disk's `url` names.
            // With MEDIA_URL=/ehb/public/storage — an install served from a
            // subdirectory — that is `ehb/public/storage/{path}`, but Laravel
            // matches routes against the request path with the app's base path
            // removed: a browser asking for /ehb/public/storage/x.jpg arrives
            // as /storage/x.jpg and never matches it. Registering the reachable
            // form here means uploads are served whether or not the web server
            // can follow `public/storage` (a Windows junction, a host without
            // symlinks, a stale directory).
            //
            // With an unprefixed URL (the usual deployment) Laravel's own route
            // is registered later at this same URI and answers instead, so this
            // one is inert.
            Route::get('storage/{path}', function (Request $request, string $path) {
                return (new ServeFile(
                    'public',
                    config('filesystems.disks.public'),
                    app()->isProduction(),
                ))($request, $path);
            })->where('path', '.*')->name('storage.public.fallback');
        },
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
