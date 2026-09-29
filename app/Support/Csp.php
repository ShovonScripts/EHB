<?php

namespace App\Support;

/**
 * Per-request nonce for the Content-Security-Policy `script-src`.
 *
 * The public site's only inline script is the JSON-LD block in the layout.
 * `'unsafe-inline'` would allow it — and would also allow any script an
 * attacker could inject, which is the whole thing a CSP exists to prevent. A
 * nonce authorises that one block and nothing else, so the strict public
 * policy can drop `'unsafe-inline'` and `'unsafe-eval'` entirely.
 *
 * Rotated once per request by SecurityHeaders, which is also where it is
 * shared with the views. Rotating per request (rather than per session) is
 * what makes it a nonce: the value is unpredictable to an attacker and is
 * never reused, so a value that leaks into a cached or archived page is
 * worthless to them.
 */
class Csp
{
    /**
     * Placeholder written into the markup in place of a real nonce.
     *
     * A per-request nonce must never be baked into a body that
     * CachePublicResponses might store: a later request replaying that body
     * would receive a nonce that does not match its own policy, and the
     * browser would refuse to execute the block. For the one inline script
     * this site has, that failure is invisible — a 200, a correct-looking
     * page, and no structured data for search engines.
     *
     * So the markup carries this literal, and SecurityHeaders swaps in the
     * real nonce on the way out, after the cache has taken its copy.
     */
    public const PLACEHOLDER = '__CSP_NONCE__';

    /** Nonce for the current request; null until SecurityHeaders rotates it. */
    protected static ?string $nonce = null;

    /** A fresh, unpredictable nonce for this request. */
    public static function rotate(): string
    {
        return static::$nonce = base64_encode(random_bytes(16));
    }

    /**
     * Replace the placeholder in a rendered body with this request's nonce.
     *
     * Runs after the response cache has stored whatever it is going to store,
     * so the cached copy keeps the placeholder and every visitor receives a
     * nonce that matches the policy sent alongside it.
     */
    public static function apply(string $body, ?string $nonce = null): string
    {
        $nonce ??= static::nonce();

        if (! str_contains($body, self::PLACEHOLDER)) {
            return $body;
        }

        return str_replace(self::PLACEHOLDER, $nonce, $body);
    }

    /**
     * The current request's nonce.
     *
     * Rotates on first access if SecurityHeaders has not run — which happens
     * for a console-rendered view or a test that renders the layout directly.
     * Returning a valid value rather than an empty string matters: an empty
     * `nonce=""` would not match any policy, whereas a rotated one matches a
     * policy the caller built from the same call.
     */
    public static function nonce(): string
    {
        return static::$nonce ??= static::rotate();
    }

    /** Forget the nonce. Called between tests so state cannot leak. */
    public static function flush(): void
    {
        static::$nonce = null;
    }
}
