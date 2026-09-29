<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentItem;
use App\Models\Publication;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The browse axes for the outlet archive: /sections (the sections the outlet
 * files bylines under) and /topics (the beat dossiers), plus the /work filter
 * rows that link into them.
 */
class BrowseStructureTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private Publication $publication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create(['role' => 'owner']);

        $this->publication = Publication::create([
            'name' => 'The Daily Star',
            'slug' => 'the-daily-star',
            'website_url' => 'https://www.thedailystar.net',
        ]);
    }

    private function section(string $slug, string $name, int $order = 1): Category
    {
        return Category::create(['name' => $name, 'slug' => $slug, 'sort_order' => $order]);
    }

    private function topic(string $slug, string $name, int $order = 1): Topic
    {
        return Topic::create(['name' => $name, 'slug' => $slug, 'sort_order' => $order]);
    }

    private function article(array $overrides = []): ContentItem
    {
        return ContentItem::create(array_merge([
            'author_id' => $this->author->id,
            'content_type' => 'news',
            'source_type' => 'external',
            'title' => 'A piece of reporting',
            'slug' => Str::slug(($overrides['title'] ?? 'piece').'-'.uniqid()),
            'summary' => 'A summary.',
            'publication_id' => $this->publication->id,
            'external_url' => 'https://www.thedailystar.net/news/story-1',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    /** /sections lists only sections that have published pieces. */
    public function test_sections_index_lists_only_populated_sections(): void
    {
        $populated = $this->section('crime-justice', 'Crime & Justice');
        $this->section('culture', 'Culture', 2);

        $this->article(['category_id' => $populated->id]);

        $response = $this->get('/sections');

        $response->assertOk()
            ->assertSee('Crime & Justice')
            ->assertDontSee('Culture');
    }

    /** A section with no published pieces 404s rather than rendering empty. */
    public function test_empty_section_page_is_not_found(): void
    {
        $this->section('culture', 'Culture');

        $this->get('/sections/culture')->assertNotFound();
        $this->get('/sections/nope')->assertNotFound();
    }

    /** A section page lists its own pieces and links to its beats. */
    public function test_section_page_lists_its_pieces_and_beats(): void
    {
        $section = $this->section('crime-justice', 'Crime & Justice');
        $other = $this->section('bangladesh', 'Bangladesh', 2);
        $beat = $this->topic('courts-trials', 'Courts & Trials');
        $foreignBeat = $this->topic('elections', 'Elections & Voting', 2);

        $mine = $this->article([
            'title' => 'Trial drags on for years',
            'category_id' => $section->id,
        ]);
        $mine->topics()->attach([$beat->id, $foreignBeat->id]);

        $theirs = $this->article(['title' => 'Something else entirely', 'category_id' => $other->id]);
        $theirs->topics()->attach([$foreignBeat->id]);

        $response = $this->get('/sections/crime-justice');

        $response->assertOk()
            ->assertSee('Trial drags on for years')
            ->assertDontSee('Something else entirely')
            // Only beats with pieces in *this* section are offered.
            ->assertSee('Courts & Trials');
    }

    /** Filtering a section page by one of its beats narrows the list. */
    public function test_section_page_filters_by_beat(): void
    {
        $section = $this->section('crime-justice', 'Crime & Justice');
        $courts = $this->topic('courts-trials', 'Courts & Trials');
        $fires = $this->topic('fire-safety', 'Fire, Arson & Building Safety', 2);

        $a = $this->article(['title' => 'Bench report piece', 'category_id' => $section->id]);
        $a->topics()->attach([$courts->id]);
        $b = $this->article(['title' => 'Warehouse burn piece', 'category_id' => $section->id]);
        $b->topics()->attach([$fires->id]);

        $this->get('/sections/crime-justice?topic=courts-trials')
            ->assertOk()
            ->assertSee('Bench report piece')
            ->assertDontSee('Warehouse burn piece');
    }

    /** /topics lists only dossiers that have published pieces. */
    public function test_topics_index_lists_only_populated_dossiers(): void
    {
        $populated = $this->topic('courts-trials', 'Courts & Trials');
        $this->topic('elections', 'Elections & Voting', 2);

        $this->article()->topics()->attach([$populated->id]);

        $response = $this->get('/topics');

        $response->assertOk()
            ->assertSee('Courts & Trials')
            ->assertDontSee('Elections & Voting');
    }

    /** /work filters compose: choosing a section keeps the other axes. */
    public function test_work_filters_compose(): void
    {
        $section = $this->section('crime-justice', 'Crime & Justice');
        $other = $this->section('bangladesh', 'Bangladesh', 2);
        $beat = $this->topic('courts-trials', 'Courts & Trials');

        $a = $this->article([
            'title' => 'Court piece in crime section',
            'category_id' => $section->id,
            'published_at' => now()->subYear(),
        ]);
        $a->topics()->attach([$beat->id]);

        $b = $this->article(['title' => 'Court piece in bangladesh section', 'category_id' => $other->id]);
        $b->topics()->attach([$beat->id]);

        $this->get('/work?category=crime-justice')
            ->assertOk()
            ->assertSee('Court piece in crime section')
            ->assertDontSee('Court piece in bangladesh section');

        // Year narrows within the section.
        $this->get('/work?category=crime-justice&topic=courts-trials&year='.now()->subYear()->format('Y'))
            ->assertOk()
            ->assertSee('Court piece in crime section');

        // The wrong year for that piece returns nothing.
        $this->get('/work?category=crime-justice&year='.now()->format('Y'))
            ->assertOk()
            ->assertDontSee('Court piece in crime section');
    }

    /** A filter chip carries the other active axes rather than dropping them. */
    public function test_work_chips_preserve_other_axes(): void
    {
        $section = $this->section('crime-justice', 'Crime & Justice');
        $this->topic('courts-trials', 'Courts & Trials');
        $this->article(['category_id' => $section->id])
            ->topics()->attach([Topic::where('slug', 'courts-trials')->value('id')]);

        $html = $this->get('/work?category=crime-justice')->assertOk()->getContent();

        // The beat chip while a section is active must keep the section.
        $this->assertStringContainsString('topic=courts-trials', $html);
        $this->assertStringContainsString('category=crime-justice', $html);
    }

    /** An unmatched filter combination renders the empty state, not a 500. */
    public function test_work_empty_filter_state(): void
    {
        $this->section('crime-justice', 'Crime & Justice');
        $this->article();

        $this->get('/work?category=crime-justice&year=1999')
            ->assertOk()
            ->assertSee('No pieces match', false);
    }

    /** The on-site content_type pages list only work hosted here, not the outlet archive. */
    public function test_content_type_sections_exclude_external_work(): void
    {
        $section = $this->section('crime-justice', 'Crime & Justice');
        $this->article(['title' => 'Outlet piece', 'category_id' => $section->id]);

        $this->get('/articles')
            ->assertOk()
            ->assertDontSee('Outlet piece');
    }

    /** A hosted piece does show on its content_type page. */
    public function test_content_type_sections_include_internal_work(): void
    {
        $this->article([
            'title' => 'A hosted piece',
            'source_type' => 'internal',
            'body' => '<p>Body copy.</p>',
            'external_url' => null,
        ]);

        $this->get('/articles')->assertOk()->assertSee('A hosted piece');
    }

    /** Both new index routes are in the sitemap, with their populated children. */
    public function test_sitemap_includes_new_axes(): void
    {
        $section = $this->section('crime-justice', 'Crime & Justice');
        $beat = $this->topic('courts-trials', 'Courts & Trials');
        $this->article(['category_id' => $section->id])->topics()->attach([$beat->id]);

        $locations = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString(url('/sections'), $locations);
        $this->assertStringContainsString(url('/topics'), $locations);
        $this->assertStringContainsString(route('sections.show', 'crime-justice'), $locations);
        $this->assertStringContainsString(route('topics.show', 'courts-trials'), $locations);
    }

    /** The archive filter form only offers terms that can match something. */
    public function test_archive_offers_only_populated_terms(): void
    {
        $populated = $this->section('crime-justice', 'Crime & Justice');
        $this->section('culture', 'Culture', 2);
        $this->article(['category_id' => $populated->id]);

        $html = $this->get('/archive')->assertOk()->getContent();

        $this->assertStringContainsString('value="crime-justice"', $html);
        $this->assertStringNotContainsString('value="culture"', $html);
    }

    /** SEO.md §13 — the unfiltered archive is indexable, its filter states are not. */
    public function test_filtered_views_are_noindex(): void
    {
        $section = $this->section('crime-justice', 'Crime & Justice');
        $this->topic('courts-trials', 'Courts & Trials');
        $this->article(['category_id' => $section->id]);

        $this->get('/work')->assertDontSee('noindex', false);
        $this->get('/work?category=crime-justice')->assertSee('noindex', false);
        $this->get('/work?year='.now()->format('Y'))->assertSee('noindex', false);

        $this->get('/sections/crime-justice')->assertDontSee('noindex', false);
        $this->get('/sections/crime-justice?topic=courts-trials')->assertSee('noindex', false);
    }

    /**
     * A page past the end of a listing must 404, not render an empty 200.
     * Otherwise `?page=` accepts any integer and an unbounded set of empty,
     * indexable pages exists — and the empty state blames the filters, which
     * were never the problem.
     */
    public function test_out_of_range_pagination_is_not_found(): void
    {
        $section = $this->section('crime-justice', 'Crime & Justice');
        $beat = $this->topic('courts-trials', 'Courts & Trials');

        // 21 pieces at 20 per page = 2 pages on /work and /sections/{slug}.
        foreach (range(1, 21) as $n) {
            $item = $this->article([
                'title' => 'Piece number '.$n,
                'category_id' => $section->id,
                'published_at' => now()->subDays($n),
            ]);
            $item->topics()->attach([$beat->id]);
        }

        // Last page is fine, one past it is not.
        $this->get('/work?page=2')->assertOk();
        $this->get('/work?page=3')->assertNotFound();
        $this->get('/work?page=99')->assertNotFound();

        $this->get('/sections/crime-justice?page=2')->assertOk();
        $this->get('/sections/crime-justice?page=3')->assertNotFound();

        $this->get('/archive?page=2')->assertOk();
        $this->get('/archive?page=3')->assertNotFound();

        $this->get('/topics/courts-trials?page=2')->assertOk();
        $this->get('/topics/courts-trials?page=3')->assertNotFound();
    }

    /**
     * The mirror case: page 1 of a filter combination that genuinely matches
     * nothing is a real empty state, not a 404. Only page > 1 is out of range.
     */
    public function test_empty_first_page_is_still_ok(): void
    {
        $this->section('crime-justice', 'Crime & Justice');
        $this->article();

        $this->get('/work?category=culture')->assertOk();
        $this->get('/work?year=1999')->assertOk();
        $this->get('/search?q=zzzznothing')->assertOk();

        // Page 2 of those is out of range, because page 1 had nothing on it.
        $this->get('/work?category=culture&page=2')->assertNotFound();
    }
}
