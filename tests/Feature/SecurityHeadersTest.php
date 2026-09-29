<?php

namespace Tests\Feature;

use App\Http\Middleware\CachePublicResponses;
use App\Models\JournalistProfile;
use App\Models\Redirect;
use App\Models\User;
use App\Support\Csp;
use App\Support\PageCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    /** SECURITY.md §19 — all required headers present on public responses. */
    public function test_public_page_carries_all_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy');
        $response->assertHeader('Content-Security-Policy');
    }

    /** CSP covers the real stack: Vite, fonts, video frames, locked frame-ancestors. */
    public function test_csp_directives_match_stack_requirements(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
        // Google Fonts (layout head).
        $this->assertStringContainsString('https://fonts.googleapis.com', $csp);
        $this->assertStringContainsString('https://fonts.gstatic.com', $csp);
        // Embedded video iframes (sections/show video_url).
        $this->assertStringContainsString("frame-src 'self' https:", $csp);
        // Lockdown directives.
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
    }

    /** Headers also present on 404s and on redirect responses. */
    public function test_headers_present_on_error_and_redirect_responses(): void
    {
        $notFound = $this->get('/definitely-missing');
        $notFound->assertNotFound();
        $notFound->assertHeader('X-Content-Type-Options', 'nosniff');
        $notFound->assertHeader('Content-Security-Policy');

        Redirect::create([
            'from_path' => 'legacy-for-headers',
            'to_path' => 'about',
            'status_code' => 301,
        ]);

        $redirect = $this->get('/legacy-for-headers');
        $redirect->assertStatus(301);
        $redirect->assertHeader('X-Content-Type-Options', 'nosniff');
        $redirect->assertHeader('Content-Security-Policy');
    }

    /** Admin panel responses are covered too (same global middleware). */
    public function test_admin_login_page_carries_security_headers(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Content-Security-Policy');
    }

    /**
     * The public site runs no Alpine and its Vite bundle contains no `eval`,
     * so it does not need the keywords that make a CSP decorative. The admin
     * panel does need them, which is why the policy is now path-aware.
     */
    public function test_public_policy_omits_unsafe_inline_and_unsafe_eval(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('unsafe-inline', $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[^']+'/", $csp);
        $this->assertMatchesRegularExpression("/style-src 'self' https:\/\/fonts\.googleapis\.com;/", $csp);
    }

    /** Filament runs Alpine (eval) and Livewire (inline styles); it needs both. */
    public function test_admin_policy_keeps_unsafe_inline_and_unsafe_eval(): void
    {
        $csp = $this->get('/admin/login')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self' 'unsafe-inline' 'unsafe-eval'", $csp);
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline'", $csp);
    }

    /**
     * The load-bearing test.
     *
     * A strict `script-src` blocks any inline script without a matching nonce.
     * If the layout's JSON-LD block lost its nonce the page would still return
     * 200 and look perfect — the browser would simply refuse to run the block,
     * silently killing the structured data that search engines read. The only
     * way to catch that is to compare the nonce in the header against the one
     * in the markup.
     */
    public function test_the_json_ld_block_carries_the_policies_nonce(): void
    {
        $response = $this->get('/');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertSame(
            1,
            preg_match("/'nonce-([^']+)'/", $csp, $matches),
            'The public policy must contain a nonce.'
        );

        $nonce = $matches[1];

        $this->assertStringContainsString(
            'nonce="'.$nonce.'"',
            $response->getContent(),
            'The inline JSON-LD script must carry the same nonce the policy authorises.'
        );
    }

    /** A nonce reused across requests would be a static secret, not a nonce. */
    public function test_the_nonce_is_rotated_per_request(): void
    {
        $first = $this->nonceFrom($this->get('/articles')->headers->get('Content-Security-Policy'));
        $second = $this->nonceFrom($this->get('/articles')->headers->get('Content-Security-Policy'));

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertNotSame($first, $second, 'A per-request nonce must differ between requests.');
    }

    /**
     * The cache/nonce interaction.
     *
     * These two features were implemented independently and silently broke
     * each other: a per-request nonce baked into the rendered body stops
     * matching the policy once CachePublicResponses replays that body to a
     * later request. The page still returns 200 and looks correct — the
     * browser just refuses the JSON-LD, so the structured data search engines
     * read disappears with nothing in the logs to say why.
     *
     * The fix is that the stored copy keeps a placeholder and the real nonce
     * is substituted on the way out. This asserts the observable contract on
     * both the MISS and the HIT path.
     */
    public function test_the_json_ld_nonce_matches_the_policy_on_a_cache_hit(): void
    {
        $this->get('/')->assertHeader(CachePublicResponses::HEADER, CachePublicResponses::MISS);

        $hit = $this->get('/');

        $hit->assertHeader(CachePublicResponses::HEADER, CachePublicResponses::HIT);

        $this->assertNonceMatchesPolicy($hit);
        $this->assertStringNotContainsString(
            Csp::PLACEHOLDER,
            $hit->getContent(),
            'The placeholder must never reach a visitor.'
        );
    }

    /** The stored copy must keep the placeholder, or the next HIT is stale. */
    public function test_the_cached_copy_keeps_the_placeholder(): void
    {
        $this->get('/')->assertHeader(CachePublicResponses::HEADER, CachePublicResponses::MISS);

        $key = PageCache::key('response', [url('/'), []]);
        $cached = PageCache::payload($key);

        $this->assertIsArray($cached);
        $this->assertStringContainsString(
            Csp::PLACEHOLDER,
            $cached['content'],
            'The cached body must keep the placeholder so each visitor gets their own nonce.'
        );
    }

    /**
     * Filament's file-upload.js spawns its image processing in a Web Worker
     * built from a `blob:` URL.
     *
     * With no explicit `worker-src`, a browser falls back to `script-src` for
     * workers, and `script-src` intentionally does not list `blob:` — so the
     * worker was blocked. The symptom was not a console error an admin would
     * report; it was an upload widget that accepted a file and then spun
     * forever, FilePond's `requestAnimationFrame` poll loop waiting on a worker
     * that never started.
     */
    public function test_admin_policy_permits_blob_workers_for_file_uploads(): void
    {
        $csp = $this->get('/admin/login')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("worker-src 'self' blob:", $csp);
    }

    /**
     * A page with an upload field must be covered, not just the login screen —
     * this is the directive that was actually missing where it mattered.
     */
    public function test_the_profile_edit_page_permits_blob_workers(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $profile = JournalistProfile::create([
            'user_id' => $owner->id,
            'name' => 'Probe',
            'title' => 'Reporter',
            'short_bio' => 'x',
            'long_bio' => 'y',
            'skills' => [],
            'social_links' => [],
        ]);

        $csp = $this->actingAs($owner)
            ->get("/admin/journalist-profile/{$profile->getKey()}/edit")
            ->assertOk()
            ->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("worker-src 'self' blob:", $csp);
    }

    /**
     * The public site creates no workers, so the grant stays off there. A
     * `blob:` worker is same-origin, so this is cheap to keep tight — but it
     * should be a deliberate omission, not an oversight.
     */
    public function test_the_public_policy_does_not_grant_blob_workers(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('worker-src', $csp);
    }

    /** Every admin page with a file field needs it, not just the profile. */
    public function test_every_upload_bearing_admin_page_permits_blob_workers(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        foreach ([
            '/admin/media/create',
            '/admin/topics/create',
            '/admin/publications/create',
            '/admin/awards/create',
        ] as $uri) {
            $csp = $this->actingAs($owner)->get($uri)->headers->get('Content-Security-Policy');

            $this->assertStringContainsString(
                "worker-src 'self' blob:",
                (string) $csp,
                "{$uri} has a file field and needs worker-src."
            );
        }
    }

    /** A filtered-out directive must not leave a stray separator behind. */
    public function test_no_empty_directives_in_either_policy(): void
    {
        foreach (['/', '/admin/login'] as $uri) {
            $csp = $this->get($uri)->headers->get('Content-Security-Policy');

            $this->assertStringNotContainsString('; ;', (string) $csp);
            $this->assertStringNotContainsString('; ;', (string) $csp);
            $this->assertDoesNotMatchRegularExpression('/(^|;\s*);/', (string) $csp, "Empty directive in {$uri}");
        }
    }

    private function assertNonceMatchesPolicy(TestResponse $response): void
    {
        preg_match("/'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'), $policy);
        preg_match('/nonce="([^"]+)"/', (string) $response->getContent(), $markup);

        $this->assertNotEmpty($policy[1] ?? null, 'The policy must carry a nonce.');
        $this->assertNotEmpty($markup[1] ?? null, 'The JSON-LD block must carry a nonce.');
        $this->assertSame(
            $policy[1],
            $markup[1],
            'The nonce in the markup must be the one the policy authorises.'
        );
    }

    private function nonceFrom(?string $csp): ?string
    {
        return preg_match("/'nonce-([^']+)'/", (string) $csp, $m) ? $m[1] : null;
    }
}
