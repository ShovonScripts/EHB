<?php

namespace Tests\Feature;

use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DemoContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedTest extends TestCase
{
    use RefreshDatabase;

    private function seedContent(): void
    {
        $this->seed(AdminUserSeeder::class);
        $this->seed(DemoContentSeeder::class);
    }

    /** SEO.md §10 — site-wide feed is valid RSS 2.0 with published items. */
    public function test_site_feed_returns_valid_rss(): void
    {
        $this->seedContent();

        $response = $this->get('/feed');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'Feed must be valid XML');
        $this->assertSame('rss', $xml->getName());
        $this->assertSame('2.0', (string) $xml['version']);

        $content = $response->getContent();
        $this->assertStringContainsString('Internal news item', $content);
        $this->assertStringContainsString(url('/articles/demo-internal-news-one'), $content);
        // External items link to their canonical /work summary page.
        $this->assertStringContainsString(url('/work/demo-external-news-one'), $content);
    }

    /** Draft and future-scheduled items must never enter the feed. */
    public function test_feed_excludes_draft_and_scheduled(): void
    {
        $this->seedContent();

        $content = $this->get('/feed')->assertOk()->getContent();

        $this->assertStringNotContainsString('demo-draft-hidden', $content);
        $this->assertStringNotContainsString('demo-scheduled-future-hidden', $content);
    }

    /** SEO.md §10 — per-section feeds scoped to their content_types. */
    public function test_section_feeds_are_type_scoped(): void
    {
        $this->seedContent();

        $articles = $this->get('/articles/feed')->assertOk()->getContent();
        $this->assertStringContainsString('Internal news item', $articles);
        $this->assertStringNotContainsString('Investigation / long-form', $articles);

        $investigations = $this->get('/investigations/feed')->assertOk()->getContent();
        $this->assertStringContainsString('Investigation / long-form', $investigations);
        $this->assertStringNotContainsString('Internal news item', $investigations);

        $interviews = $this->get('/interviews/feed')->assertOk()->getContent();
        $this->assertStringContainsString('Interview item', $interviews);

        $opinions = $this->get('/opinions/feed')->assertOk()->getContent();
        $this->assertStringContainsString('Opinion / analysis item', $opinions);
    }

    /** Section feeds are valid RSS and self-referential (atom:link rel=self). */
    public function test_section_feeds_are_valid_rss_with_self_link(): void
    {
        $this->seedContent();

        $response = $this->get('/multimedia/feed')->assertOk();

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        $this->assertSame('rss', $xml->getName());

        $selfLinks = $xml->channel->children('http://www.w3.org/2005/Atom')->link;
        $this->assertNotEmpty($selfLinks);
        $this->assertSame(url('/multimedia/feed'), (string) $selfLinks->attributes()['href']);
    }

    /** Route-order guard: /articles/feed must not be swallowed by /articles/{slug}. */
    public function test_section_feed_route_shadows_detail_wildcard(): void
    {
        $this->seedContent();

        $this->get('/articles/feed')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    /** Layout advertises the feed for reader autodiscovery. */
    public function test_layout_emits_feed_autodiscovery_link(): void
    {
        $this->seedContent();

        $this->get('/')
            ->assertOk()
            ->assertSee('rel="alternate" type="application/rss+xml"', false);
    }
}
