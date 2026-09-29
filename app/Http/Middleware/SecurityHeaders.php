<?php

namespace App\Http\Middleware;

use App\Support\Csp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SECURITY.md §19 — security headers on every response.
 *
 * The policy is deliberately *different* for the admin panel and the public
 * site, because their requirements are genuinely different.
 *
 * The admin panel runs Filament: Alpine compiles expressions at runtime
 * (`eval`), Livewire sets inline styles, and both are load-bearing. It gets
 * `'unsafe-inline' 'unsafe-eval'`.
 *
 * The public site uses neither. `resources/js/app.js` is plain JavaScript, the
 * Vite bundle contains no `eval` or `new Function`, and no view sets an inline
 * style attribute. The single inline script is the JSON-LD block in the
 * layout, which a nonce authorises precisely. So the public policy drops both
 * keywords, and that is a large part of the policy's value: with
 * `'unsafe-inline'` present, a CSP stops defending against injected script
 * almost entirely.
 *
 * Serving the permissive policy to anonymous visitors meant the admin's
 * requirements — not the public site's — were setting the ceiling for every
 * reader. Google Fonts and https-only media/frames are still allowed, so the
 * pages render exactly as before.
 */
class SecurityHeaders
{
    /** Paths that get the permissive (Filament/Alpine) policy. */
    private const ADMIN_PATHS = ['admin', 'admin/*'];

    public function handle(Request $request, Closure $next): Response
    {
        // Rotated per request. It is substituted into the body on the way out,
        // not into the view on the way in — see the note in handle() below.
        $nonce = Csp::rotate();

        $response = $next($request);

        // This middleware is the outermost in the stack, so the response cache
        // has already taken its copy by the time this runs. That ordering is
        // load-bearing: the cached copy keeps Csp::PLACEHOLDER, and only the
        // copy actually sent to this visitor gets a real nonce. Substituting
        // any earlier (in the view, say) would bake one request's nonce into a
        // body served to the next, and the browser would silently drop the
        // JSON-LD block.
        if (is_string($response->getContent())) {
            $response->setContent(Csp::apply($response->getContent(), $nonce));
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'accelerometer=(), camera=(), geolocation=(), gyroscope=(), microphone=(), payment=(), usb=()'
        );
        $response->headers->set('Content-Security-Policy', $this->csp($request, $nonce));

        // SECURITY.md §18 — HSTS once traffic is actually HTTPS.
        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }

    private function csp(Request $request, string $nonce): string
    {
        $isAdmin = $request->is(...self::ADMIN_PATHS);

        $scriptSrc = $isAdmin
            ? "'self' 'unsafe-inline' 'unsafe-eval'"
            // The nonce authorises the layout's JSON-LD block and nothing else.
            : "'self' 'nonce-{$nonce}'";

        // Inline styles are a Filament/Livewire requirement; the public site
        // sets none, so it does not need the keyword.
        $styleSrc = $isAdmin
            ? "'self' 'unsafe-inline' https://fonts.googleapis.com"
            : "'self' https://fonts.googleapis.com";

        $connectSrc = "'self'";

        if (config('app.env') !== 'production') {
            // Vite HMR during local development.
            $connectSrc .= ' ws://localhost:* http://localhost:*';
        }

        return implode('; ', array_filter([
            "default-src 'self'",
            "script-src {$scriptSrc}",
            // Filament's file-upload.js runs its image processing in a Web
            // Worker built from a `blob:` URL. Without an explicit
            // `worker-src`, a browser falls back to `script-src` for workers —
            // and `script-src` deliberately does not list `blob:`, so the
            // worker was blocked outright. The visible symptom was an upload
            // widget that accepted a file and then spun forever: FilePond's
            // `requestAnimationFrame` poll loop waiting on a worker that never
            // started.
            //
            // `blob:` is safe here because a blob URL is same-origin and can
            // only be constructed by script already running on this origin.
            // Admin only: the public site creates no workers, so it does not
            // need the grant, and leaving it off keeps that policy tighter.
            $isAdmin ? "worker-src 'self' blob:" : null,
            "style-src {$styleSrc}",
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: blob: https:",
            "frame-src 'self' https:",
            "media-src 'self' blob: https:",
            "connect-src {$connectSrc}",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ])).';';
    }
}
