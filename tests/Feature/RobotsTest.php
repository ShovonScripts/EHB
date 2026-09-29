<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RobotsTest extends TestCase
{
    use RefreshDatabase;

    /** SEO.md §9 — allow-all plus admin/search exclusions and sitemap pointer. */
    public function test_robots_txt_disallows_admin_and_search_query_strings(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        $content = $response->getContent();

        $this->assertStringContainsString('User-agent: *', $content);
        $this->assertStringContainsString('Disallow: /admin', $content);
        $this->assertStringContainsString('Disallow: /search?', $content);
        $this->assertStringContainsString('Sitemap: '.url('/sitemap.xml'), $content);

        // Public sections must remain crawlable — no blanket disallow.
        $this->assertStringNotContainsString("\nDisallow: /\n", $content);
        $this->assertStringNotContainsString('Disallow: /articles', $content);
    }

    /** The static public/robots.txt must not shadow the dynamic route. */
    public function test_robots_txt_is_served_by_route_not_static_file(): void
    {
        $this->assertFileDoesNotExist(public_path('robots.txt'));
        $this->get('/robots.txt')->assertOk();
    }
}
