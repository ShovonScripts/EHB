<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * DATABASE.md redirects table — resolve legacy paths so slug changes
 * made in the admin don't 404 (SEO preservation).
 *
 * Registered as global middleware: unmatched paths never reach route
 * middleware, and matched-but-missing-slug detail pages (which do hit
 * a route) are handled before the controller aborts. The whole map is
 * cached and invalidated by Redirect model events.
 */
class ApplyRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $path = trim($request->path(), '/');

        if ($path === '') {
            return $next($request);
        }

        $redirect = Cache::remember(
            'redirects.map',
            now()->addHour(),
            fn () => Redirect::query()
                ->get(['from_path', 'to_path', 'status_code'])
                ->mapWithKeys(fn (Redirect $row) => [
                    trim($row->from_path, '/') => [
                        'to' => trim($row->to_path, '/'),
                        'status' => in_array($row->status_code, [301, 302], true)
                            ? $row->status_code
                            : 301,
                    ],
                ])
                ->all()
        );

        if (! isset($redirect[$path])) {
            return $next($request);
        }

        $target = $redirect[$path];

        // Loop guard: a row pointing at itself (or empty) is invalid.
        if ($target['to'] === '' || $target['to'] === $path) {
            return $next($request);
        }

        // Defence in depth. The admin form already restricts `to_path` to a
        // site-relative path, but rows can also arrive from a seeder, a raw
        // insert, or an older row saved before that validation existed.
        //
        // `redirect()->to('/'.$to)` is prefixed with a slash precisely so a
        // stored `//evil.com` cannot become an open redirect, and it does not
        // — it becomes `///evil.com`. That is still a broken redirect, so
        // anything that is not plainly a path is ignored rather than served:
        // a 404 is a correct answer for a redirect that does not make sense,
        // whereas a 301 to a mangled URL is silently worse than no rule at all.
        if (! self::isSafeTarget($target['to'])) {
            Log::warning('Ignoring a redirect with an unusable target', [
                'from' => $path,
                'to' => $target['to'],
            ]);

            return $next($request);
        }

        return redirect()->to('/'.$target['to'], $target['status'], [], []);
    }

    /**
     * True when a stored target is a plain path on this site.
     *
     * Rejects anything carrying a scheme (`https:`, `javascript:`), anything
     * protocol-relative (`//host`), anything with a backslash (browsers treat
     * `/\host` as protocol-relative too), and control characters.
     */
    private static function isSafeTarget(string $target): bool
    {
        return ! str_contains($target, '\\')
            && ! str_contains($target, '//')
            && ! preg_match('/[\x00-\x1F\x7F]/', $target)
            && ! preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $target);
    }
}
