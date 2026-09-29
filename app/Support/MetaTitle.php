<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Compose the `<title>` and `og:title` for a page (SEO.md §1).
 *
 * ## Why this is not just string concatenation in the layout
 *
 * The obvious implementation — `'<title> | '.$siteName` — produced titles
 * that no search result could display. This site archives long outlet
 * headlines, and 75% of them came out over 60 characters once the site name
 * was appended, the worst at 120. A SERP shows roughly the first 60
 * characters, so the journalist's own name was being pushed off the end of
 * every one of those results: the branding was invisible exactly where it is
 * most useful, and the part of the headline that did survive was the front of
 * a sentence that had been cut mid-word.
 *
 * So the composed title is bounded, and the bound is spent on the suffix
 * first. Truncating the *composed* string would keep the site name and throw
 * away the end of the headline, which reads worse than a clean cut.
 *
 * ## What is deliberately not truncated
 *
 * The visible `<h1>` on the page. It renders `$item->title` directly and is
 * never routed through here — a reader gets the whole headline, only the
 * search-result preview is shortened.
 */
class MetaTitle
{
    /**
     * Budget for the whole composed title, in characters.
     *
     * A character budget is an approximation of what actually matters (SERP
     * width, in pixels, which depends on the font and the device), but it is
     * the same approximation every SEO tool makes, and a predictable one is
     * easier to reason about and to test than a pixel measurement.
     */
    public const LIMIT = 60;

    /** Appended when a title is cut, so the truncation is never silent. */
    private const ELLIPSIS = '…';

    /**
     * Build the composed title.
     *
     * @param  string|null  $title  the page/entity title, or null on the homepage
     * @param  string  $siteName  the site name from Settings
     * @param  string  $tagline  used only when there is no title at all
     */
    public static function compose(?string $title, string $siteName, string $tagline = ''): string
    {
        $title = trim((string) $title);

        if ($title === '') {
            return static::cut($siteName.' — '.$tagline, self::LIMIT);
        }

        // Don't repeat the site name when the title already carries it. On a
        // single-author site `site_name` is the journalist's own name, so a
        // naive suffix renders "About Emrul Hasan Bappi | Emrul Hasan Bappi".
        if ($siteName !== '' && Str::contains($title, $siteName)) {
            return static::cut($title, self::LIMIT);
        }

        if ($siteName === '') {
            return static::cut($title, self::LIMIT);
        }

        $suffix = ' | '.$siteName;

        // The suffix always survives: a title that is cut back to nothing but
        // the site name is worse than a shortened headline, and losing the
        // branding from every long-titled result is the problem this fixes.
        if (mb_strlen($title) + mb_strlen($suffix) <= self::LIMIT) {
            return $title.$suffix;
        }

        return static::cut($title, self::LIMIT - mb_strlen($suffix)).$suffix;
    }

    /**
     * Shorten to a character budget, appending an ellipsis if anything was lost.
     *
     * Multibyte-safe (titles contain Bangla) and prefers a word boundary when
     * one is close, so the cut does not land mid-word.
     */
    private static function cut(string $value, int $limit): string
    {
        if ($limit < 1 || mb_strlen($value) <= $limit) {
            return $value;
        }

        $budget = $limit - mb_strlen(self::ELLIPSIS);

        if ($budget < 1) {
            return mb_substr($value, 0, max(1, $limit));
        }

        $clipped = mb_substr($value, 0, $budget);

        // Prefer breaking on whitespace, but only if that keeps most of the
        // budget — otherwise "a" would be a better cut than "half a sen".
        $lastSpace = mb_strrpos($clipped, ' ');

        if ($lastSpace !== false && $lastSpace > $budget * 0.6) {
            $clipped = rtrim(mb_substr($clipped, 0, $lastSpace));
        }

        return $clipped.self::ELLIPSIS;
    }
}
