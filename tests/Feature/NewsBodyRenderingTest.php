<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The `news_body` field on the public page.
 *
 * The journalist's own text for a piece, shown under the featured image on
 * both page types. It is offered in the admin for every piece regardless of
 * `source_type`, which is the point: an external piece — a link-out to another
 * outlet — can still carry the journalist's own reporting without the page
 * becoming a reproduction of someone else's article.
 *
 * The subtle part, and the reason this file exists, is the outbound callout.
 * Its copy used to say the article is "summarised and linked here rather than
 * reproduced". Rendering text under the image while the page still says that
 * would be a false statement on a live page, so the copy is now conditional.
 */
class NewsBodyRenderingTest extends TestCase
{
    use RefreshDatabase;

    private function item(array $overrides = []): ContentItem
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $category = Category::firstOrCreate(['slug' => 'news'], ['name' => 'News']);

        // A real featured image, because the whole point of these tests is that
        // the text lands *under the image*. With no image on the page there is
        // nothing to assert an order against.
        Storage::fake('public');
        Storage::disk('public')->put('content-items/featured/test.jpg', 'img');

        $media = Media::create([
            'type' => 'image',
            'file_path' => 'content-items/featured/test.jpg',
            'disk' => 'public',
            'original_filename' => 'test.jpg',
            'alt_text' => 'The courtroom.',
            'uploaded_by' => $owner->id,
        ]);

        return ContentItem::create(array_merge([
            'title' => 'Justice in shifts',
            'slug' => 'justice-in-shifts',
            'status' => 'published',
            'content_type' => 'news',
            'source_type' => 'external',
            'author_id' => $owner->id,
            'summary' => 'A summary of the piece.',
            'external_url' => 'https://www.thedailystar.net/news/justice-shifts-4277396',
            'category_id' => $category->id,
            'featured_image_media_id' => $media->id,
        ], $overrides));
    }

    // ── Where it appears ───────────────────────────────────────────

    public function test_it_renders_under_the_image_on_an_external_piece(): void
    {
        $item = $this->item(['news_body' => '<p>The bench list was reshuffled.</p>']);

        $html = $this->get($item->publicPath())->assertOk()->getContent();

        $imageAt = strpos($html, '<img');
        $textAt = strpos($html, 'bench list was reshuffled');
        $calloutAt = strpos($html, 'Read the original at');

        $this->assertNotFalse($textAt, 'The news body did not render at all.');
        $this->assertNotFalse($imageAt);
        $this->assertNotFalse($calloutAt);
        $this->assertLessThan($textAt, $imageAt, 'The text must come after the image.');
        $this->assertLessThan($calloutAt, $textAt, 'The outbound callout stays last.');
    }

    public function test_it_renders_on_an_internal_piece_too(): void
    {
        $item = $this->item([
            'source_type' => 'internal',
            'body' => '<p>The main article body.</p>',
            'news_body' => '<p>Additional reporting notes.</p>',
        ]);

        $this->get($item->publicPath())
            ->assertOk()
            ->assertSee('Additional reporting notes', false);
    }

    /** Formatting written in the editor must survive to the page. */
    public function test_formatting_survives_to_the_page(): void
    {
        $item = $this->item([
            'news_body' => '<h2>A heading</h2><p>Some <strong>bold</strong> text.</p>',
        ]);

        $html = $this->get($item->publicPath())->assertOk()->getContent();

        $this->assertStringContainsString('<h2>A heading</h2>', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
    }

    /** Nothing renders when the field is empty — no empty shell on the page. */
    public function test_nothing_renders_when_the_field_is_empty(): void
    {
        $item = $this->item(['news_body' => null]);

        $html = $this->get($item->publicPath())->assertOk()->getContent();

        $this->assertSame(
            0,
            substr_count($html, 'article-body'),
            'An empty news body must not leave an empty prose container behind.'
        );
    }

    /**
     * A piece written before this field existed still shows something: the
     * main body is used as a fallback rather than the page going blank.
     */
    public function test_it_falls_back_to_the_main_body(): void
    {
        $item = $this->item(['news_body' => null, 'body' => '<p>Written before the field existed.</p>']);

        $this->get($item->publicPath())
            ->assertOk()
            ->assertSee('Written before the field existed', false);
    }

    // ── The callout copy must not contradict the page ──────────────

    public function test_the_not_reproduced_claim_is_dropped_when_text_is_shown(): void
    {
        $item = $this->item(['news_body' => '<p>The journalist&rsquo;s own reporting.</p>']);

        $html = $this->get($item->publicPath())->assertOk()->getContent();

        $this->assertStringNotContainsString(
            'rather than reproduced',
            $html,
            'The page cannot claim the article is not reproduced while showing text under the image.'
        );

        $this->assertStringContainsString('published by', $html);
    }

    /** With no text, the original claim is still exactly right. */
    public function test_the_not_reproduced_claim_is_kept_when_there_is_no_text(): void
    {
        $item = $this->item(['news_body' => null, 'body' => null]);

        $this->get($item->publicPath())
            ->assertOk()
            ->assertSee('rather than reproduced', false);
    }

    /** The outbound link itself must survive either way. */
    public function test_the_outbound_link_is_always_present(): void
    {
        // Distinct slugs: `slug` is unique, so looping one fixture is not an
        // option here.
        $withText = $this->item(['news_body' => '<p>With text.</p>']);
        $withoutText = $this->item(['slug' => 'justice-in-shifts-two', 'news_body' => null]);

        foreach ([$withText, $withoutText] as $item) {
            $html = $this->get($item->publicPath())->assertOk()->getContent();

            $this->assertStringContainsString('thedailystar.net', $html);
            $this->assertStringContainsString('rel="noopener noreferrer"', $html);
        }
    }

    // ── Safety ─────────────────────────────────────────────────────

    /**
     * The page renders sanitized HTML with `{!! !!}`, so this is the assertion
     * that the sanitizer is actually load-bearing on the way to the reader.
     *
     * Asserted on the dangerous fragments rather than on `<script`, because the
     * page legitimately contains script tags of its own — the JSON-LD block and
     * the Vite bundle. An earlier version of this test asserted the bare
     * `<script` string and failed on those, which would have trained the test
     * to be ignored.
     */
    public function test_a_script_never_reaches_the_page(): void
    {
        $item = $this->item([
            'news_body' => '<p>ok</p><script>alert(1)</script><img src=x onerror=alert(2)>',
        ]);

        $html = $this->get($item->publicPath())->assertOk()->getContent();

        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringNotContainsString('alert(2)', $html);
        $this->assertStringNotContainsString('onerror', $html);

        // Sanity check that the page is intact — matched on the type rather
        // than the whole opening tag, because that tag now carries a per-request
        // CSP nonce and will not match a literal.
        $this->assertStringContainsString('application/ld+json', $html);
    }

    /** Drafts stay invisible, news body or not. */
    public function test_a_draft_is_not_public(): void
    {
        $item = $this->item(['status' => 'draft', 'news_body' => '<p>Hidden.</p>']);

        $this->get($item->publicPath())->assertNotFound();
    }
}
