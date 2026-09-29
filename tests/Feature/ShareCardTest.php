<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The social share card (og:image and friends) on detail pages.
 *
 * Two defects lived here:
 *
 * 1. `og:image:alt` always carried the site-wide default alt text when one
 *    was configured — the layout preferred it over the page's own alt, and
 *    no view passed a page alt anyway. Every share card on the site
 *    described the wrong picture.
 * 2. No `og:image:width`/`og:image:height` were emitted at all, so the first
 *    scrape of every URL risked a collapsed card with no image until the
 *    crawler revisited.
 */
class ShareCardTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->owner = User::factory()->create(['role' => 'owner']);
    }

    /** Store a real $width×$height image on the fake disk; returns the path. */
    private function storeImage(string $path, int $width = 1200, int $height = 630): string
    {
        $upload = UploadedFile::fake()->image('card.jpg', $width, $height);

        Storage::disk('public')->put($path, file_get_contents($upload->getRealPath()));

        return $path;
    }

    private function makeMedia(string $path, ?string $alt = null): Media
    {
        return Media::create([
            'type' => 'image',
            'file_path' => $path,
            'disk' => 'public',
            'original_filename' => basename($path),
            'alt_text' => $alt,
            'uploaded_by' => $this->owner->id,
        ]);
    }

    private function makeItem(array $overrides = []): ContentItem
    {
        return ContentItem::create(array_merge([
            'author_id' => $this->owner->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'A story with a share card',
            'slug' => 'a-story-with-a-share-card',
            'summary' => 'A summary for the card.',
            'body' => '<p>Body text.</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    private function meta(string $content, string $property): ?string
    {
        preg_match('~<meta property="'.preg_quote($property, '~').'" content="([^"]+)"~', $content, $m);

        return $m[1] ?? null;
    }

    // ── The card ───────────────────────────────────────────────────

    /** A dedicated OG image wins, with its own alt text and dimensions. */
    public function test_og_image_wins_with_its_own_alt_and_dimensions(): void
    {
        $og = $this->makeMedia($this->storeImage('content-items/og/card.jpg'), 'OG alt describing the card');
        $featured = $this->makeMedia($this->storeImage('content-items/featured/other.jpg', 800, 600), 'Featured alt');

        $this->makeItem([
            'seo_og_image_media_id' => $og->id,
            'featured_image_media_id' => $featured->id,
        ]);

        $content = $this->get('/articles/a-story-with-a-share-card')->assertOk()->content();

        $this->assertSame(url($og->url), $this->meta($content, 'og:image'));
        $this->assertSame('OG alt describing the card', $this->meta($content, 'og:image:alt'));
        $this->assertSame('1200', $this->meta($content, 'og:image:width'));
        $this->assertSame('630', $this->meta($content, 'og:image:height'));
    }

    /**
     * The regression: the site-wide default alt must never override the
     * page image's own alt text.
     */
    public function test_site_default_alt_does_not_override_the_page_image_alt(): void
    {
        Setting::updateOrCreate(['key' => 'default_og_image_alt'], ['value' => 'SITE DEFAULT ALT']);
        SiteSettings::flush();

        $featured = $this->makeMedia($this->storeImage('content-items/featured/page.jpg'), 'PAGE IMAGE ALT');

        $this->makeItem(['featured_image_media_id' => $featured->id]);

        $content = $this->get('/articles/a-story-with-a-share-card')->assertOk()->content();

        $this->assertSame('PAGE IMAGE ALT', $this->meta($content, 'og:image:alt'));
        $this->assertStringNotContainsString('SITE DEFAULT ALT', $content);
    }

    /** Without an OG image, the featured image (and its alt) is the card. */
    public function test_featured_image_is_the_share_fallback(): void
    {
        $featured = $this->makeMedia($this->storeImage('content-items/featured/page.jpg', 1600, 900), 'Featured alt text');

        $this->makeItem(['featured_image_media_id' => $featured->id]);

        $content = $this->get('/articles/a-story-with-a-share-card')->assertOk()->content();

        $this->assertSame(url($featured->url), $this->meta($content, 'og:image'));
        $this->assertSame('Featured alt text', $this->meta($content, 'og:image:alt'));
        $this->assertSame('1600', $this->meta($content, 'og:image:width'));
        $this->assertSame('900', $this->meta($content, 'og:image:height'));
    }

    /** With no page image at all, the site default carries its own alt. */
    public function test_site_default_image_carries_the_default_alt(): void
    {
        $card = $this->makeMedia($this->storeImage('settings/og/card.jpg'));

        Setting::updateOrCreate(['key' => 'default_og_image_media_id'], ['value' => $card->id]);
        Setting::updateOrCreate(['key' => 'default_og_image_alt'], ['value' => 'Default card alt']);
        SiteSettings::flush();

        $this->makeItem();

        $content = $this->get('/articles/a-story-with-a-share-card')->assertOk()->content();

        $this->assertSame(url($card->url), $this->meta($content, 'og:image'));
        $this->assertSame('Default card alt', $this->meta($content, 'og:image:alt'));
        $this->assertSame('1200', $this->meta($content, 'og:image:width'));
    }

    /** Article pages expose publish date, section and tags to crawlers. */
    public function test_article_tags_are_emitted(): void
    {
        $category = Category::create(['name' => 'Crime & Justice', 'slug' => 'crime-justice']);
        $tag = Tag::create(['name' => 'Courts', 'slug' => 'courts']);

        $item = $this->makeItem([
            'category_id' => $category->id,
            'published_at' => now()->subDays(2),
        ]);
        $item->tags()->attach($tag->id);

        $content = $this->get('/articles/a-story-with-a-share-card')->assertOk()->content();

        $this->assertSame('article', $this->meta($content, 'og:type'));
        $this->assertSame(
            $item->published_at->toIso8601String(),
            $this->meta($content, 'article:published_time')
        );
        // Blade escapes the & — assert on the raw response, not the decoded name.
        $this->assertSame('Crime &amp; Justice', $this->meta($content, 'article:section'));
        $this->assertStringContainsString(
            '<meta property="article:tag" content="Courts">',
            $content
        );
    }

    /** The external detail page resolves the same card (it is a second view). */
    public function test_external_detail_resolves_the_same_card(): void
    {
        $og = $this->makeMedia($this->storeImage('content-items/og/ext.jpg'), 'External OG alt');

        $this->makeItem([
            'source_type' => 'external',
            'external_url' => 'https://example.com/original',
            'seo_og_image_media_id' => $og->id,
        ]);

        $content = $this->get('/work/a-story-with-a-share-card')->assertOk()->content();

        $this->assertSame(url($og->url), $this->meta($content, 'og:image'));
        $this->assertSame('External OG alt', $this->meta($content, 'og:image:alt'));
        $this->assertSame('article', $this->meta($content, 'og:type'));
    }

    // ── Dimension capture ──────────────────────────────────────────

    /** Uploaded bytes are measured on save — no extra query, no backfill. */
    public function test_dimensions_are_captured_on_save(): void
    {
        $media = $this->makeMedia($this->storeImage('content-items/featured/measured.jpg', 1200, 630));

        $this->assertSame(1200, $media->width);
        $this->assertSame(630, $media->height);
    }

    /** A missing or non-image file leaves dimensions null, never throws. */
    public function test_unmeasurable_files_leave_dimensions_null(): void
    {
        Storage::disk('public')->put('content-items/featured/bogus.jpg', 'not-an-image');

        $bogus = $this->makeMedia('content-items/featured/bogus.jpg');
        $missing = $this->makeMedia('content-items/featured/does-not-exist.jpg');

        $this->assertNull($bogus->width);
        $this->assertNull($bogus->height);
        $this->assertNull($missing->width);
        $this->assertNull($missing->height);
    }

    /** The backfill command measures rows that predate dimension capture. */
    public function test_backfill_command_fills_missing_dimensions(): void
    {
        Storage::disk('public')->put('content-items/featured/old.jpg', 'not-an-image');

        $media = $this->makeMedia('content-items/featured/old.jpg');

        $this->assertNull($media->width);

        // The file lands later (a restore, a re-upload to the same path).
        $this->storeImage('content-items/featured/old.jpg', 640, 480);

        $this->artisan('media:backfill-dimensions')->assertSuccessful();

        $this->assertSame(640, $media->fresh()->width);
        $this->assertSame(480, $media->fresh()->height);
    }
}
