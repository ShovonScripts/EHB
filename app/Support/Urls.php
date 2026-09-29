<?php

namespace App\Support;

/**
 * URL helpers for the one conversion the app cannot avoid: turning a
 * root-relative asset URL into something a crawler can follow.
 *
 * Media URLs are deliberately root-relative (see MediaUrlTest and
 * config/filesystems.php) because a URL built from APP_URL points every image
 * at whatever host that setting happens to name — which is how every image on
 * the site once 404'd from a preview domain. A page's <img src> therefore
 * carries `/storage/…`, and a browser resolves it against the page's origin.
 *
 * Open Graph, Twitter cards, JSON-LD and the sitemap are read by software that
 * resolves nothing, so those need an absolute URL. The conversion is not
 * `url($relative)`: `url()` prepends the application's base path, and under an
 * install in a subdirectory (see the MEDIA_URL note in config/filesystems.php)
 * the relative URL already carries that base path — `url()` would duplicate it
 * and produce a URL that 404s. Taking the scheme and host from the app root and
 * appending the path as-is is correct in both layouts, because that is exactly
 * what a browser does with a root-relative URL.
 */
class Urls
{
    /**
     * Scheme and host (with port) of the app root — no path.
     */
    public static function origin(): string
    {
        return (string) (preg_replace('#^(https?://[^/]+).*$#', '$1', url('/')) ?? '');
    }

    /**
     * A root-relative URL made absolute; anything already absolute (including a
     * protocol-relative `//host/…`) is returned unchanged.
     *
     * Null and the empty string pass through, so callers can hand in an
     * optional value without guarding first.
     */
    public static function absolute(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        if (preg_match('#^(https?:)?//#i', $url) === 1) {
            return $url;
        }

        return self::origin().'/'.ltrim($url, '/');
    }
}
