<?php

namespace Tests\Feature;

use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedirectTest extends TestCase
{
    use RefreshDatabase;

    /** A seeded redirect fires with its stored status code (DATABASE.md redirects). */
    public function test_seeded_redirect_fires_with_configured_status(): void
    {
        Redirect::create([
            'from_path' => 'old-article-slug',
            'to_path' => 'articles/demo-internal-news-one',
            'status_code' => 301,
        ]);

        $this->get('/old-article-slug')
            ->assertRedirect('/articles/demo-internal-news-one')
            ->assertStatus(301);
    }

    public function test_temporary_redirect_uses_302(): void
    {
        Redirect::create([
            'from_path' => 'temporary-path',
            'to_path' => 'about',
            'status_code' => 302,
        ]);

        $this->get('/temporary-path')->assertStatus(302)->assertRedirect('/about');
    }

    /** Path normalization: leading/trailing slashes stored or requested. */
    public function test_paths_are_normalized_on_both_sides(): void
    {
        Redirect::create([
            'from_path' => '/legacy/slug/',
            'to_path' => '/articles/demo-internal-news-one',
            'status_code' => 301,
        ]);

        $this->get('/legacy/slug')
            ->assertStatus(301)
            ->assertRedirect('/articles/demo-internal-news-one');
    }

    /** Non-GET requests pass through untouched (redirects are for GET/HEAD only). */
    public function test_non_get_requests_are_not_redirected(): void
    {
        Redirect::create([
            'from_path' => 'old-path',
            'to_path' => 'about',
            'status_code' => 301,
        ]);

        // POST to a path with no matching route → 404, never a redirect.
        $this->post('/old-path')->assertNotFound();
    }

    /** A self-referencing redirect must be ignored (loop guard). */
    public function test_self_redirect_is_ignored(): void
    {
        Redirect::create([
            'from_path' => 'loop-path',
            'to_path' => '/loop-path',
            'status_code' => 301,
        ]);

        $this->get('/loop-path')->assertNotFound();
    }

    /** Paths without a redirect row behave normally (404 or route). */
    public function test_unmatched_paths_still_404(): void
    {
        $this->get('/definitely-not-a-page')->assertNotFound();
        $this->get('/')->assertOk();
    }

    /** Redirect map is cached, but creating a row invalidates it immediately. */
    public function test_creating_redirect_invalidates_cache_within_request_cycle(): void
    {
        // Prime the cache with an empty map.
        $this->get('/not-yet-redirected')->assertNotFound();

        Redirect::create([
            'from_path' => 'not-yet-redirected',
            'to_path' => 'about',
            'status_code' => 301,
        ]);

        $this->get('/not-yet-redirected')
            ->assertStatus(301)
            ->assertRedirect('/about');
    }

    /**
     * A stored target that is not a plain path never redirects off-site.
     *
     * The admin form rejects these, but rows can also arrive from a seeder, a
     * raw insert, or a row saved before that validation existed. None of these
     * is an open redirect — the `/` prefix in `redirect()->to('/'.$to)` and the
     * `trim(..., '/')` in the map builder both see to that — but they would
     * each produce a 301 to a mangled URL, whose only symptom is that the old
     * link quietly stopped working.
     *
     * Asserted as "stays on this host" rather than a specific status: some of
     * these are refused by the middleware and some are neutralised by the
     * surrounding normalization, and both outcomes are correct. What must never
     * happen is a Location pointing at another host.
     */
    public function test_unusable_targets_never_redirect_off_site(): void
    {
        $targets = [
            'absolute-url' => 'https://evil.com/phish',
            'protocol-relative' => '//evil.com/phish',
            'backslash' => '/\\evil.com',
            'javascript-scheme' => 'javascript:alert(1)',
            'control-chars' => "/about\nX-Injected: 1",
            'schemeless-host' => 'evil.com/phish',
        ];

        foreach ($targets as $label => $target) {
            Redirect::create([
                'from_path' => 'bad-target-'.$label,
                'to_path' => $target,
                'status_code' => 301,
            ]);

            $response = $this->get('/bad-target-'.$label);
            $location = (string) $response->headers->get('Location');

            if ($location === '') {
                // Refused outright: the request fell through to a 404.
                $response->assertNotFound("A redirect to '{$target}' must not be served.");

                continue;
            }

            $this->assertStringStartsWith(
                'http://localhost/',
                $location,
                "A redirect to '{$target}' resolved off-site: {$location}"
            );
        }
    }

    /** A valid nested path is unaffected by the guard. */
    public function test_nested_paths_still_redirect(): void
    {
        Redirect::create([
            'from_path' => 'deep/legacy/path',
            'to_path' => '/articles/demo-internal-news-one',
            'status_code' => 301,
        ]);

        $this->get('/deep/legacy/path')
            ->assertStatus(301)
            ->assertRedirect('/articles/demo-internal-news-one');
    }
}
