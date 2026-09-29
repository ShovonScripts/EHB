<?php

namespace Tests\Feature;

use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DemoContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicRoutesTest extends TestCase
{
    use RefreshDatabase;

    private function seedContent(): void
    {
        $this->seed(AdminUserSeeder::class);
        $this->seed(DemoContentSeeder::class);
    }

    /** Every listing page renders (FR-101..FR-113). */
    public function test_listing_pages_render(): void
    {
        $this->seedContent();

        foreach ([
            '/', '/about', '/articles', '/investigations', '/interviews',
            '/opinions', '/multimedia', '/other', '/work', '/publications',
            '/archive', '/search?q=demo', '/contact',
            '/publications/the-daily-star',
            '/topics/crime-justice',
        ] as $uri) {
            $this->get($uri)->assertOk();
        }
    }

    /** Internal detail renders with full body (FR-114 / FR-201). */
    public function test_internal_detail_renders_full_body(): void
    {
        $this->seedContent();

        $response = $this->get('/articles/demo-internal-news-one');

        $response->assertOk()
            ->assertSee('Internal news item', false)
            ->assertSee('reading-column typography', false)
            ->assertSee('rel="canonical"', false);
    }

    /**
     * External detail shows outbound callout, never a full body (FR-114/115).
     *
     * The callout is the focal point of the page, so it carries a heading that
     * names the outlet and states plainly that the article is not reproduced
     * here. The raw URL is deliberately not printed: the button already points
     * there, and a 90-character address wrapped mid-word told the reader
     * nothing the button had not.
     */
    public function test_external_detail_shows_outbound_link(): void
    {
        $this->seedContent();

        $response = $this->get('/work/demo-external-news-one');

        $response->assertOk()
            ->assertSee('Read Original Article', false)
            ->assertSee('https://example.com/replace-with-real-article-url', false)
            ->assertSee('The full report', false)
            ->assertSee('Read the original at', false)
            ->assertSee('rather than reproduced', false);
    }

    /**
     * The page must orient the reader: which section it was filed under, and
     * which beat or tag it belongs to. Previously the beats and tags sat below
     * the callout as bare pills with nothing naming them, so a lone chip read as
     * decoration, and the outlet section was not shown at all.
     *
     * This fixture is filed under "Bangladesh" and carries a tag, not a topic.
     */
    public function test_external_detail_shows_section_and_tags(): void
    {
        $this->seedContent();

        $this->get('/work/demo-external-news-one')
            ->assertOk()
            ->assertSee('Filed under', false)
            ->assertSee(route('sections.show', 'bangladesh'), false)
            ->assertSee(route('archive', ['tag' => 'law-enforcement']), false);
    }

    /**
     * A piece that does carry beat dossiers must link them, since a beat is the
     * only way to find the rest of a story that the outlet filed under
     * different sections.
     */
    public function test_external_detail_shows_beats(): void
    {
        $this->seedContent();

        $this->get('/work/demo-external-opinion-one')
            ->assertOk()
            ->assertSee('Filed under', false)
            ->assertSee(route('topics.show', 'human-rights'), false);
    }

    /**
     * The breadcrumb must agree with the nav it sits under. It used to say
     * "External Work" while the nav, footer and /work itself all said
     * "Reporting".
     */
    public function test_external_detail_breadcrumb_matches_current_naming(): void
    {
        $this->seedContent();

        $this->get('/work/demo-external-news-one')
            ->assertOk()
            ->assertSee('Reporting', false)
            ->assertDontSee('External Work', false);
    }

    /** Section URL for an external piece 301s to its canonical /work URL (SEO.md §3). */
    public function test_section_url_for_external_redirects_to_work(): void
    {
        $this->seedContent();

        $this->get('/articles/demo-external-news-one')
            ->assertRedirect('/work/demo-external-news-one');
    }

    /** Drafts and future-scheduled items are never visible (FR-203/204). */
    public function test_draft_and_future_scheduled_are_hidden(): void
    {
        $this->seedContent();

        $this->get('/articles/demo-draft-hidden')->assertNotFound();
        $this->get('/articles/demo-scheduled-future-hidden')->assertNotFound();

        $this->get('/archive')
            ->assertOk()
            ->assertDontSee('Draft (not publicly visible)', false)
            ->assertDontSee('Scheduled (not publicly visible yet)', false);
    }

    /** Archive filters combine without errors (FR-111). */
    public function test_archive_filters_combine(): void
    {
        $this->seedContent();

        $this->get('/archive?type=news&source=internal&category=crime&year='.date('Y'))
            ->assertOk()
            ->assertDontSee('External work (link-out entry)', false);

        $this->get('/archive?type=news&source=external')
            ->assertOk()
            ->assertSee('External work (link-out entry)', false);
    }

    /** Search returns relevant results across content types (FR-112). */
    public function test_search_returns_results(): void
    {
        $this->seedContent();

        $this->get('/search?q=demo')
            ->assertOk()
            ->assertSee('results', false)
            ->assertSee('Internal news item', false);

        // Search pages are noindex (SEO.md §9).
        $this->get('/search?q=demo')
            ->assertSee('noindex', false);

        $this->get('/search?q=zzzznotfound')->assertOk();
    }

    /** Filtered archive permutations are noindex (SEO.md §13). */
    public function test_filtered_archive_is_noindex(): void
    {
        $this->seedContent();

        $this->get('/archive?type=news')->assertSee('noindex', false);
        $this->get('/archive')->assertDontSee('noindex', false);
    }

    /** Unknown pages hit the on-brand 404 (NFR-009). */
    public function test_unknown_page_returns_404_page(): void
    {
        $this->seedContent();

        $this->get('/definitely-not-a-page')
            ->assertNotFound()
            ->assertSee('404', false);
    }
}
