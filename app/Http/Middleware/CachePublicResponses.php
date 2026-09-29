<?php

namespace App\Http\Middleware;

use App\Support\PageCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ROADMAP Phase 8 (Performance) — short-TTL response cache for public pages.
 *
 * The site is single-author and overwhelmingly read traffic, so whole
 * anonymous HTML responses for stable pages are cached for a couple of
 * minutes. Invalidation is version-based (PageCache): any content change
 * bumps the version tag, which changes every cache key immediately, so a
 * publish is visible on the very next request (see RecordsActivity).
 *
 * Deliberately conservative about what it will store:
 * - anonymous GET/HEAD only — never admin or Livewire traffic;
 * - HTTP 200 with an HTML body only — never 404s, redirects, or API/XML;
 * - never a body carrying per-visitor state (a CSRF token, a flash message),
 *   because handing one visitor another's token is exactly the kind of bug
 *   response caching is notorious for.
 *
 * ## Why "sets a cookie" is the wrong test
 *
 * The obvious guard is to refuse any response carrying `Set-Cookie`. On this
 * stack that refuses *everything*: `VerifyCsrfToken::handle()` attaches an
 * `XSRF-TOKEN` cookie to every single response, including anonymous GETs, and
 * `StartSession` adds the session cookie. So the guard as originally written
 * meant the cache never stored a single byte — the feature was inert.
 *
 * The real invariant is narrower and is enforced in two places instead:
 *
 * 1. **Cookies are never replayed.** `storableHeaders()` whitelists four
 *    content headers only, so a `Set-Cookie` cannot survive a round trip
 *    through the cache regardless of what the original response carried.
 * 2. **Per-visitor state in the body is never stored** — see `isStorable()`.
 *
 * A cookie the *application* sets deliberately (a consent flag, a locale
 * preference) is still refused, because unlike the framework's two it varies
 * per visitor and the body would reflect it.
 */
class CachePublicResponses
{
    /** Marks cache behaviour for observability and tests. */
    public const HEADER = 'X-Page-Cache';

    /** The response came from the cache. */
    public const HIT = 'HIT';

    /** The response was rendered and stored for next time. */
    public const MISS = 'MISS';

    /**
     * The request was never eligible (admin, Livewire, search, archive, or an
     * authenticated user), so the cache was not consulted at all.
     */
    public const BYPASS = 'BYPASS';

    public function handle(Request $request, Closure $next): Response
    {
        if (! PageCache::requestIsCacheable($request)) {
            $response = $next($request);
            $response->headers->set(self::HEADER, self::BYPASS);

            return $response;
        }

        $key = PageCache::key('response', [
            $request->url(),
            // The query is parsed and canonicalized rather than used raw, so
            // `?page=2` and `?page[]=2` (and a reordering of the same params)
            // share one entry instead of each storing a full copy of the page.
            $request->query(),
        ]);

        $cached = PageCache::payload($key);

        if (is_array($cached)) {
            $response = response($cached['content'], $cached['status'], $cached['headers']);
            $response->headers->set(self::HEADER, self::HIT);

            return $response;
        }

        $response = $next($request);

        if ($this->isStorable($response)) {
            PageCache::put($key, [
                'content' => $response->getContent(),
                'status' => $response->getStatusCode(),
                'headers' => $this->storableHeaders($response),
            ]);
        }

        $response->headers->set(self::HEADER, self::MISS);

        return $response;
    }

    /** Only HTML 200s whose body carries no per-visitor state. */
    private function isStorable(Response $response): bool
    {
        if (! $response->isSuccessful()) {
            return false;
        }

        if ($response->headers->getCacheControlDirective('no-store')) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        if (! str_contains($contentType, 'text/html')) {
            return false;
        }

        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return false;
        }

        if ($this->carriesVisitorState($content)) {
            return false;
        }

        return ! $this->setsApplicationCookie($response);
    }

    /**
     * True when the rendered body embeds something that belongs to the session
     * that requested it.
     *
     * A CSRF token is the important case: it is minted per session, and a
     * cached page would hand visitor B the token minted for visitor A, so B's
     * next form POST fails with a 419. The flash-message case is the same
     * shape — `/contact` renders `session('status')`, so one visitor's
     * "thank you" confirmation would greet the next visitor.
     *
     * Detected from the body rather than from the URL so a form added to any
     * page later is covered automatically, with no list to keep in sync.
     *
     * `data-csrf` is Livewire's own per-session token, injected into the markup
     * by SupportAutoInjectedAssets whenever any Livewire component renders on
     * the page. The public frontend is plain Blade and never uses Livewire, but
     * the admin panel does, and a shared layout or composer could pull that
     * into a public response later — so it is treated the same as a form
     * token rather than assumed absent.
     */
    private function carriesVisitorState(string $content): bool
    {
        return str_contains($content, 'name="_token"')
            || str_contains($content, 'name="_method"')
            || str_contains($content, 'data-csrf=');
    }

    /**
     * True when the response sets a cookie the application chose.
     *
     * `XSRF-TOKEN` and the session cookie are set on every response by the
     * framework and are ignored here: they are not replayed (see
     * `storableHeaders()`), and the session's CSRF token is never embedded in
     * a body that passed `carriesVisitorState()`. Anything else — a consent
     * flag, a locale, an A/B bucket — varies per visitor and changes the
     * markup, so it disqualifies the response.
     */
    private function setsApplicationCookie(Response $response): bool
    {
        $frameworkCookies = [
            'XSRF-TOKEN',
            (string) config('session.cookie'),
        ];

        foreach ($response->headers->getCookies() as $cookie) {
            if (! in_array($cookie->getName(), $frameworkCookies, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Headers worth replaying on a hit. Security headers are applied by
     * SecurityHeaders on the way out of both paths, so they are intentionally
     * left out here.
     *
     * @return array<string, string>
     */
    private function storableHeaders(Response $response): array
    {
        $headers = [];

        foreach (['Content-Type', 'Content-Language', 'Last-Modified', 'ETag'] as $name) {
            if ($response->headers->has($name)) {
                $headers[$name] = (string) $response->headers->get($name);
            }
        }

        return $headers;
    }
}
