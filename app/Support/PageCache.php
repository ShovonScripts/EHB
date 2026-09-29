<?php

namespace App\Support;

use App\Models\Award;
use App\Models\CareerHistory;
use App\Models\Category;
use App\Models\ContentItem;
use App\Models\Education;
use App\Models\JournalistProfile;
use App\Models\Media;
use App\Models\Page;
use App\Models\Publication;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * ROADMAP Phase 8 (Performance) — short-lived caching for public reads.
 *
 * The site is single-author and read-heavy, so listing queries can be cached
 * with a small, deterministic TTL. Every key is version-tagged, and the TTL is
 * deliberately short (a couple of minutes) because the failure mode we care
 * about is a stale public page, not a thundering herd.
 *
 * Invalidation is belt-and-braces:
 * - Any audited model change (SECURITY.md §15 / RecordsActivity) bumps the
 *   content version, which changes every key prefix immediately.
 * - TTL expiry covers changes made outside the app (e.g. direct DB edits).
 */
class PageCache
{
    /** Version tag key; bumped whenever published content changes. */
    public const VERSION_KEY = 'page-cache:content-version';

    /** Lifetime (seconds) of a cached listing/fragment. */
    public const TTL = 120;

    /**
     * Key for a fragment: version-tagged so a publish invalidates everything.
     *
     * Parameters are canonicalized first — see normalize(). The cache key is
     * attacker-reachable (it is built from the query string), so unbounded
     * distinct keys are a storage-exhaustion vector, not just untidiness.
     */
    public static function key(string $namespace, array $parameters = []): string
    {
        return implode(':', [
            'page-cache',
            'v'.self::contentVersion(),
            $namespace,
            md5(serialize(self::normalize($parameters))),
        ]);
    }

    /**
     * Longest canonical parameter string that will be hashed.
     *
     * A key must stay inside the cache store's key column (`string`, 255 chars
     * on MySQL) even though it is hashed afterwards, because the namespace is
     * concatenated un-hashed and callers pass free-form namespaces. Anything
     * longer is truncated before hashing, which is safe: a truncated key is
     * still deterministic, it just means two very long parameter sets could
     * collide — and colliding is a cache miss at worst, never wrong content,
     * because the value stored under a key is the fully-computed result.
     */
    private const MAX_KEY_LENGTH = 200;

    /**
     * Reduce a parameter array to one canonical form per distinct query.
     *
     * The problem this solves: a cache keyed on raw request input grows
     * without bound, because a query string has far more spellings than it has
     * meanings. `?year=2024`, `?year[]=2024`, and `?year[]=2024&year[]=2025`
     * all describe the same request but each mints its own `mediumText` row in
     * the `cache` table, holding a full rendered page. An anonymous visitor
     * could grow that table indefinitely by appending array parameters, and
     * nothing would ever read those rows back to expire them.
     *
     * The rules:
     * - `null` and `''` are dropped, so `?year=` and no `year` at all agree
     *   with how the controllers already read filters (`?:` and `ctype_digit`
     *   both treat them as absent).
     * - Arrays are collapsed to their sorted, de-duplicated scalar members, so
     *   `?year[]=2024&year[]=2025` and the reverse order agree.
     * - Keys are sorted, so parameter order in the query string is irrelevant.
     * - Everything is cast to string, so `?page=2` and `?page[]=2` cannot
     *   produce different structures.
     */
    public static function normalize(array $parameters): array
    {
        $normalized = [];

        foreach ($parameters as $key => $value) {
            $key = (string) $key;

            if (is_array($value)) {
                $members = self::normalizeArray($value);

                if ($members === []) {
                    continue;
                }

                $normalized[$key] = $members;

                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $normalized[$key] = is_bool($value)
                ? ($value ? '1' : '0')
                : (string) $value;
        }

        ksort($normalized);

        return self::truncate($normalized);
    }

    /**
     * Flatten a nested array to sorted, unique scalars.
     *
     * Nested arrays (`?year[a]=b`) have no meaning to any controller, so they
     * are reduced to their leaf values purely to keep the key space finite.
     * Truncating the member count matters as much as truncating the length: a
     * request with 10,000 array members must not produce a 10,000-element key.
     */
    private static function normalizeArray(array $value): array
    {
        $members = [];

        array_walk_recursive($value, function ($leaf) use (&$members): void {
            if (is_bool($leaf)) {
                $members[] = $leaf ? '1' : '0';
            } elseif (is_scalar($leaf) && (string) $leaf !== '') {
                $members[] = (string) $leaf;
            }
        });

        $members = array_values(array_unique($members));
        sort($members, SORT_STRING);

        return array_slice($members, 0, self::MAX_KEY_LENGTH);
    }

    /**
     * Keep the canonical form within the key column's width.
     *
     * An oversized parameter is replaced by a digest of its full value rather
     * than dropped. Dropping it would be a correctness bug, not just a missed
     * optimisation: a key that omitted the parameter would collide with the
     * key for the same URL *without* it, so `/articles?q=<5KB of text>` would
     * be served the cached `/articles` page. Hashing keeps distinct inputs
     * distinct while bounding the key.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    private static function truncate(array $parameters): array
    {
        $bounded = [];

        foreach ($parameters as $key => $value) {
            $candidate = $bounded + [$key => $value];

            if (strlen(serialize($candidate)) <= self::MAX_KEY_LENGTH) {
                $bounded = $candidate;

                continue;
            }

            // Out of room: keep the entry, but only as a digest of what it was.
            $bounded[$key] = 'sha256:'.substr(hash('sha256', serialize($value)), 0, 32);
        }

        return $bounded;
    }

    /**
     * Current content version — changes on every publish/update/delete of a
     * cache-relevant model.
     */
    public static function contentVersion(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    /** Invalidate every cached public page. */
    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, self::contentVersion() + 1);
    }

    /**
     * Remember a value for a short TTL. Cache failures (e.g. a driver that is
     * down) must never break the request — fall back to computing directly.
     */
    public static function remember(string $namespace, array $parameters, \Closure $callback): mixed
    {
        try {
            return Cache::remember(self::key($namespace, $parameters), self::TTL, $callback);
        } catch (\Throwable $exception) {
            report($exception);

            return $callback();
        }
    }

    /** Read a raw cached payload (used by the response cache middleware). */
    public static function payload(string $key): mixed
    {
        try {
            return Cache::get($key);
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /** Write a raw payload for the short TTL. */
    public static function put(string $key, mixed $value): void
    {
        try {
            Cache::put($key, $value, self::TTL);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * True when a response/lookup may be cached: anonymous GETs only, never
     * authenticated admin traffic, never query-string variations of pages we
     * deliberately keep out of the index (archive filters, search).
     */
    public static function requestIsCacheable(Request $request): bool
    {
        return $request->isMethod('GET')
            && ! $request->user()
            && ! $request->is('admin', 'admin/*', 'livewire/*')
            && ! $request->is('archive', 'search');
    }

    /**
     * Models whose changes should invalidate the public page cache.
     *
     * Media is on the list because a media row *is* public markup: its URL,
     * alt text and caption are rendered into pages, and the whole response is
     * cached for the TTL. Without it, editing a row's alt text, replacing the
     * file behind a featured image, or deleting an image left the cached HTML
     * pointing at the old description — or at a file that no longer exists, so
     * the page served a broken image until the TTL expired. Note that a Media
     * change also has to drop SiteSettings (the share image falls back to the
     * portrait), which RecordsActivity already does for anything on this list.
     */
    public static function shouldInvalidate(Model $model): bool
    {
        return in_array($model->getMorphClass(), [
            ContentItem::class,
            Category::class,
            Tag::class,
            Topic::class,
            Publication::class,
            Media::class,
            JournalistProfile::class,
            CareerHistory::class,
            Education::class,
            Award::class,
            Page::class,
            Setting::class,
        ], true);
    }
}
