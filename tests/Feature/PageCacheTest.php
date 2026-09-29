<?php

namespace Tests\Feature;

use App\Http\Middleware\CachePublicResponses;
use App\Models\Category;
use App\Models\ContentItem;
use App\Models\Page;
use App\Models\User;
use App\Support\PageCache;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DemoContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * ROADMAP Phase 8 — the whole-response cache for anonymous public pages.
 *
 * These tests exist because the feature was silently inert: `isStorable()`
 * refused any response carrying `Set-Cookie`, and the framework attaches an
 * `XSRF-TOKEN` cookie to *every* response, so nothing was ever stored and
 * every request reported MISS. A cache that cannot be observed to work is a
 * cache nobody notices is broken, so the HIT/MISS contract is asserted here
 * directly rather than inferred from a performance number.
 */
class PageCacheTest extends TestCase
{
    use RefreshDatabase;

    /** A published internal item, so the listing and detail pages are real. */
    private function seedContent(): void
    {
        $this->seed(AdminUserSeeder::class);
        $this->seed(DemoContentSeeder::class);
    }

    public function test_public_page_is_served_from_cache_on_the_second_request(): void
    {
        $this->seedContent();

        $this->get('/')->assertHeader(CachePublicResponses::HEADER, CachePublicResponses::MISS);
        $this->get('/')->assertHeader(CachePublicResponses::HEADER, CachePublicResponses::HIT);
    }

    /**
     * The point of the cache is to serve the same page, so that is asserted
     * rather than assumed.
     *
     * Compared with the CSP nonce normalized out, because a nonce is
     * deliberately per-request: SecurityHeaders substitutes a fresh one into
     * every response it sends, including a HIT, so that each visitor's markup
     * matches their own policy. The bytes are otherwise identical, and that
     * is what this guards — a drifted shared fragment, a missing eager load, a
     * cache key that is quietly serving the wrong page.
     *
     * `TestCase::setUp()` resets Livewire's cross-test injection flag so the
     * comparison is not at the mercy of test ordering.
     */
    public function test_cached_response_matches_the_original(): void
    {
        $this->seedContent();

        $miss = $this->get('/');
        $hit = $this->get('/');

        $hit->assertHeader(CachePublicResponses::HEADER, CachePublicResponses::HIT);
        $this->assertSame($miss->getStatusCode(), $hit->getStatusCode());
        $this->assertSame(
            $this->withoutCspNonce($miss->getContent()),
            $this->withoutCspNonce($hit->getContent()),
            'A cache hit must serve the same page that was stored.'
        );
    }

    /** Replace the per-request nonce with a fixed token so bodies compare. */
    private function withoutCspNonce(string $body): string
    {
        return preg_replace('/nonce="[^"]*"/', 'nonce="[redacted]"', $body);
    }

    /**
     * A HIT is produced by this middleware, which is global and therefore runs
     * *outside* the `web` group — so `StartSession` and `VerifyCsrfToken` never
     * execute and no cookie is attached. That is the intended behaviour, but
     * it must be asserted, because if it ever regressed the other way the
     * cached response would start replaying one visitor's cookie to another.
     */
    public function test_cache_hit_never_replays_a_cookie(): void
    {
        $this->seedContent();

        $this->get('/')->assertHeader(CachePublicResponses::HEADER, CachePublicResponses::MISS);

        $hit = $this->get('/');

        $hit->assertHeader(CachePublicResponses::HEADER, CachePublicResponses::HIT);
        $this->assertFalse(
            $hit->headers->has('Set-Cookie'),
            'A cache hit must not replay cookies captured from another visitor.'
        );
    }

    /**
     * `/contact` renders `@csrf` and `session('status')`. Caching it would
     * hand one visitor the CSRF token minted for another (their next POST
     * would 419) and replay one visitor's "thank you" flash to the next.
     */
    public function test_pages_with_a_csrf_token_are_never_cached(): void
    {
        $this->seedContent();

        $this->get('/contact')->assertHeader(CachePublicResponses::HEADER, CachePublicResponses::MISS);
        $this->get('/contact')->assertHeader(CachePublicResponses::HEADER, CachePublicResponses::MISS);
        $this->get('/contact')->assertHeader(CachePublicResponses::HEADER, CachePublicResponses::MISS);
    }

    /**
     * A cookie the *application* sets (a consent flag, a locale) varies per
     * visitor and the body would reflect it, so such a response is refused
     * even though the framework's own two cookies are allowed.
     */
    public function test_application_cookies_disqualify_a_response(): void
    {
        Route::middleware('web')->get('/tmp-consent-page', function () {
            return response('<html><body>Consent page</body></html>')
                ->header('Content-Type', 'text/html')
                ->cookie('cookie_consent', 'accepted');
        });

        $this->get('/tmp-consent-page')->assertHeader(CachePublicResponses::HEADER, CachePublicResponses::MISS);
        $this->get('/tmp-consent-page')->assertHeader(CachePublicResponses::HEADER, CachePublicResponses::MISS);
    }

    /** Listing and detail pages, the paths the cache is actually for. */
    public function test_listing_and_detail_pages_are_cached(): void
    {
        $this->seedContent();

        foreach (['/work', '/articles', '/about', '/publications', '/topics'] as $uri) {
            $this->get($uri)->assertHeader(CachePublicResponses::HEADER, CachePublicResponses::MISS, $uri);
            $this->get($uri)->assertHeader(CachePublicResponses::HEADER, CachePublicResponses::HIT, $uri);
        }

        $detail = '/articles/demo-internal-news-one';

        $this->get($detail)->assertHeader(CachePublicResponses::HEADER, CachePublicResponses::MISS);
        $this->get($detail)->assertHeader(CachePublicResponses::HEADER, CachePublicResponses::HIT);
    }

    /** Distinct URLs must not collide on one cache entry. */
    public function test_different_urls_get_different_cache_entries(): void
    {
        $this->seedContent();

        $this->get('/about');
        $this->get('/publications');

        $this->get('/about')->assertHeader(CachePublicResponses::HEADER, CachePublicResponses::HIT);
        $this->get('/publications')->assertHeader(CachePublicResponses::HEADER, CachePublicResponses::HIT);
    }

    /**
     * The response cache keys on URL *and* query string, so filtered archive
     * permutations cannot serve each other's markup. The archive is excluded
     * from caching outright (PageCache::requestIsCacheable), so this asserts
     * the exclusion rather than the keying.
     */
    public function test_excluded_paths_are_never_cached(): void
    {
        $this->seedContent();

        foreach (['/search?q=demo', '/archive', '/archive?type=news', '/admin/login'] as $uri) {
            $this->get($uri)->assertHeader(CachePublicResponses::HEADER, CachePublicResponses::BYPASS, $uri);
            $this->get($uri)->assertHeader(CachePublicResponses::HEADER, CachePublicResponses::BYPASS, $uri);
        }
    }

    /** An authenticated admin must never be served another visitor's copy. */
    public function test_authenticated_requests_bypass_the_cache(): void
    {
        $this->seedContent();

        $admin = User::where('role', 'owner')->firstOrFail();

        $this->actingAs($admin)
            ->get('/about')
            ->assertHeader(CachePublicResponses::HEADER, CachePublicResponses::BYPASS);
    }

    /** Non-HTML responses (the RSS feeds) are not response-cached. */
    public function test_non_html_responses_are_never_cached(): void
    {
        $this->seedContent();

        $this->get('/feed')->assertHeader(CachePublicResponses::HEADER, CachePublicResponses::MISS);
        $this->get('/feed')->assertHeader(CachePublicResponses::HEADER, CachePublicResponses::MISS);
    }

    /**
     * 404s are not stored, so a page created after someone hit the missing URL
     * is immediately reachable rather than waiting out the TTL.
     */
    public function test_404_responses_are_not_cached(): void
    {
        $this->seedContent();

        $this->get('/not-a-real-page')->assertNotFound();
        $this->get('/not-a-real-page')->assertNotFound();

        Page::create([
            'slug' => 'not-a-real-page',
            'title' => 'Now A Real Page',
            'body' => '<p>Created after the 404.</p>',
        ]);

        $this->get('/not-a-real-page')->assertOk();
    }

    /**
     * The version tag is what makes a publish visible on the very next request
     * despite the cached listing pages (see RecordsActivity).
     */
    public function test_publishing_flushes_the_cache(): void
    {
        $this->seedContent();

        $this->get('/articles');
        $this->get('/articles')->assertHeader(CachePublicResponses::HEADER, CachePublicResponses::HIT);

        $author = User::where('role', 'owner')->firstOrFail();

        ContentItem::create([
            'author_id' => $author->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'A brand new investigation',
            'slug' => 'brand-new-investigation',
            'summary' => 'Fresh.',
            'body' => '<p>Fresh body.</p>',
            'status' => 'published',
            'published_at' => now(),
            'category_id' => Category::where('slug', 'crime')->value('id'),
        ]);

        $this->get('/articles')->assertHeader(CachePublicResponses::HEADER, CachePublicResponses::MISS);
        $this->get('/articles')->assertSee('A brand new investigation', false);
    }

    /** Version-tagged keys: a bump changes every key at once. */
    public function test_content_version_changes_the_key_prefix(): void
    {
        $before = PageCache::contentVersion();

        PageCache::flush();

        $this->assertGreaterThan($before, PageCache::contentVersion());
        $this->assertStringStartsWith('page-cache:v'.PageCache::contentVersion(), PageCache::key('response', ['/']));
    }
}
