<?php

namespace App\Support;

use App\Models\JournalistProfile;
use App\Models\Media;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Site-wide settings edited by the journalist in the admin panel
 * (FR-406 — "site identity, default SEO, social links, analytics IDs").
 *
 * Values live in the `settings` key/value table rather than `config/` so the
 * journalist can change them without a deploy or an `.env` edit. This class is
 * the single read path for them, so the layout, meta tags and feeds all agree.
 *
 * Reads are memoized for the request (the layout asks for the site name a
 * dozen times, and the default cache store is the database — one query per
 * call would be wasteful) and cached across requests with a short TTL.
 * `Setting` model events flush both layers, so a change in the admin panel is
 * live on the next request.
 */
class SiteSettings
{
    public const CACHE_KEY = 'site-settings:all';

    public const OG_CACHE_KEY = 'site-settings:og';

    /** Short TTL — only a backstop for edits made outside the admin panel. */
    public const TTL = 300;

    /** Per-request memoization. Reset by flush(). */
    protected static ?array $memo = null;

    /**
     * The default card image, resolved once per request.
     *
     * `false` means "not looked up yet" and null means "looked up, and there
     * is none" — a nullable property alone cannot tell those apart, and the
     * difference is one Media query per card in a grid instead of one per page.
     */
    protected static Media|false|null $cardImage = false;

    /**
     * The resolved site-wide share image. Same tri-state convention as
     * $cardImage: `false` = not looked up yet, null = looked up, none.
     */
    protected static Media|false|null $ogImage = false;

    /**
     * All settings as a key => value map.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        if (static::$memo !== null) {
            return static::$memo;
        }

        return static::$memo = Cache::remember(
            self::CACHE_KEY,
            self::TTL,
            fn () => Setting::query()->pluck('value', 'key')->all()
        );
    }

    /**
     * A single setting. Blank strings are treated as "not set" so an admin
     * who clears a field falls back to the default rather than emitting "".
     *
     * `"0"` and `0` are deliberately *not* treated as blank. A toggle set to
     * off, or a verification value of "0", is a real value; only null, the
     * empty string and an empty array mean "not set".
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::all()[$key] ?? null;

        if ($value === null || $value === '' || $value === []) {
            return $default;
        }

        return $value;
    }

    /** Site name — used for <title>, og:site_name, header and footer. */
    public static function siteName(): string
    {
        return (string) static::get('site_name', config('app.name'));
    }

    /** Short line under the site name on the homepage title fallback. */
    public static function siteTagline(): string
    {
        return (string) static::get(
            'site_tagline',
            config('app.description', 'Independent journalist — reporting, investigations, and analysis.')
        );
    }

    /** Fallback meta description when a page has none of its own. */
    public static function defaultSeoDescription(): string
    {
        return (string) static::get(
            'default_seo_description',
            'Personal archive and portfolio of journalistic work — reporting, investigations, interviews, and analysis.'
        );
    }

    // ── The SEO preset's accessors ─────────────────────────────────
    //
    // One per declared setting, so a typo in a key is a fatal error rather
    // than a silently empty value on a live page. Defaults come from the
    // preset, which is the single place a default is written down.

    /** Alt text for the fallback share image (og:image:alt). */
    public static function defaultOgImageAlt(): ?string
    {
        $alt = static::get('default_og_image_alt');

        return is_string($alt) && trim($alt) !== '' ? trim($alt) : null;
    }

    /**
     * The site-wide thumbnail for a card whose piece has no image of its own.
     *
     * Unlike `defaultOgImageUrl()` this deliberately does *not* fall through to
     * the journalist's portrait. A share is one image, a grid is twenty, and
     * the same face twenty times over reads as a rendering bug rather than as
     * a fallback. Unset therefore means "no picture", which is exactly what
     * those cards looked like before this setting existed.
     *
     * An id that does not resolve — mistyped, or pointing at a Media row that
     * was deleted — resolves to null the same way, so a stale value costs a
     * card its image and never costs the page an exception.
     *
     * Resolved once per request, because the grid asks for it once per card.
     * Not cached across requests: the row holds a URL and alt text the panel
     * can edit without touching settings, so there is no flush hook here, and
     * one query per request is cheaper than a stale thumbnail.
     */
    public static function defaultCardImage(): ?Media
    {
        if (static::$cardImage !== false) {
            return static::$cardImage;
        }

        $configured = static::get('default_card_image_media_id');

        return static::$cardImage = $configured ? Media::find($configured) : null;
    }

    /** The default card image's URL, or null when there is no usable one. */
    public static function defaultCardImageUrl(): ?string
    {
        return static::defaultCardImage()?->url;
    }

    /**
     * The X / Twitter handle, normalised to a bare handle with no `@`.
     *
     * Normalised rather than trusted: the value is pasted by hand from a
     * profile URL, so "@bappi", "bappi" and "https://x.com/bappi" are all
     * things someone will actually type, and `twitter:site` wants `@bappi`.
     * Anything that does not contain a usable handle yields null so no
     * malformed tag is emitted.
     */
    public static function twitterHandle(): ?string
    {
        $raw = static::get('twitter_handle');

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        // Accept a pasted profile URL, with or without a scheme or www.
        if (preg_match('#^(?:https?://)?(?:www\.)?(?:x|twitter)\.com/@?([A-Za-z0-9_]{1,15})#i', trim($raw), $m)) {
            return $m[1];
        }

        // Otherwise treat it as a bare handle.
        if (preg_match('/^@?([A-Za-z0-9_]{1,15})$/', trim($raw), $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Whether the site may be indexed at all.
     *
     * Defaults to true: a site that is live wants to be found, and the
     * failure mode of getting this wrong is invisible — pages simply never
     * appear in results with nothing on the page to say why.
     */
    public static function isIndexable(): bool
    {
        $value = static::get('indexable', SeoSettings::defaultFor('indexable'));

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }

    /** The Google Search Console verification token, if set. */
    public static function googleSiteVerification(): ?string
    {
        $value = static::get('google_site_verification');

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Extra robots.txt directives, one per line, blanks removed.
     *
     * Anything that is not a `Directive: value` line is dropped rather than
     * written to the file: robots.txt is fetched by anonymous crawlers, and a
     * malformed line is at best ignored and at worst read as a path to
     * disallow.
     *
     * @return array<int, string>
     */
    public static function extraRobotsRules(): array
    {
        $raw = static::get('extra_robots_rules');

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $rules = [];

        foreach (preg_split('/\R/', $raw) as $line) {
            $line = trim($line);

            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$directive, $value] = array_map('trim', explode(':', $line, 2));

            if ($directive === '' || $value === '' || preg_match('#^[A-Za-z-]+$#', $directive) !== 1) {
                continue;
            }

            $rules[] = $directive.': '.$value;
        }

        return $rules;
    }

    /** The public contact email, if the journalist chose to publish one. */
    public static function contactEmail(): ?string
    {
        $value = static::get('contact_email');

        return is_string($value) && filter_var(trim($value), FILTER_VALIDATE_EMAIL) ? trim($value) : null;
    }

    /**
     * Site-wide fallback OG image (SEO.md §2 — "featured image or default").
     *
     * Resolution order:
     *   1. `default_og_image_media_id` — an explicitly chosen card, edited in
     *      Admin → Settings. Always wins if set *and resolvable*.
     *   2. The journalist's portrait.
     *   3. null, and the layout then emits no `og:image` tag at all.
     *
     * Step 2 matters more than it looks. Every one of this site's pieces is a
     * link-out with no body image of its own, so with no default configured
     * there was nothing for any of the 210 articles to fall back to, and every
     * share of his work rendered a blank card. On a single-author site the
     * portrait *is* the social card, and deriving it means a fresh install has
     * working social previews without anyone having to configure a setting.
     *
     * A configured id that does not resolve — mistyped, or pointing at a Media
     * row that was deleted — falls through to step 2 rather than to step 3.
     * Returning null there used to be the worst available outcome: the layout
     * omits `og:image` entirely, so one bad value silently stripped the image
     * from every share on the site. Failing soft to the portrait costs nothing
     * and keeps the card working.
     *
     * Cached separately from `all()` so the media lookup does not run on every
     * request that renders a page without its own image.
     */
    public static function defaultOgImageUrl(): ?string
    {
        $configured = static::get('default_og_image_media_id');

        return Cache::remember(self::OG_CACHE_KEY, self::TTL, function () use ($configured) {
            if ($configured) {
                // `?? portraitUrl()`, not `?->url` on its own: an id that does
                // not resolve must degrade to the portrait, never to null. See
                // the resolution order in the docblock above.
                return Media::find($configured)?->url ?? static::portraitUrl();
            }

            return static::portraitUrl();
        });
    }

    /** The journalist's portrait URL, or null when none is uploaded. */
    public static function portraitUrl(): ?string
    {
        return JournalistProfile::current()?->photo?->url;
    }

    /**
     * Analytics is deliberately NOT supported.
     *
     * `google_analytics_id` may exist as a row, but this site ships a strict
     * Content-Security-Policy (SECURITY.md §19) with no third-party script
     * origins. Loading GA would require allow-listing google-analytics.com
     * and googletagmanager.com in `script-src`, weakening the header for every
     * visitor in exchange for a metric the single-author site can live without.
     * A self-hosted, first-party analytics script is the supported path.
     */
    public static function flush(): void
    {
        static::$memo = null;
        static::$cardImage = false;
        static::$ogImage = false;

        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::OG_CACHE_KEY);
    }
}
