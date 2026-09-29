<?php

namespace Tests\Feature;

use App\Filament\Resources\ContentItems\Pages\EditContentItem;
use App\Filament\Resources\Media\Pages\CreateMedia;
use App\Http\Middleware\CachePublicResponses;
use App\Models\ContentItem;
use App\Models\JournalistProfile;
use App\Models\Media;
use App\Models\Setting;
use App\Models\User;
use App\Support\PageCache;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The image pipeline, end to end.
 *
 * Every case here comes from the same failure shape: the admin panel shows a
 * picture, the database says the picture is there, and the *public* site does
 * something else — serves nothing, serves a stale version of it, or describes
 * it wrongly to a crawler. Those go unnoticed for weeks because no single layer
 * reports an error:
 *
 *  - a sitemap `<image:loc>` that is root-relative, which Google discards, so
 *    every featured image is invisible to image search;
 *  - a Media row created through the library screen that cannot be saved at
 *    all, because the read-only uploader field is never written;
 *  - an alt-text edit that bypasses Media's model events, so the cached page
 *    keeps the old description and the audit trail misses the change;
 *  - an `og:image:alt` that describes the configured share image while
 *    `og:image` points at the piece's own picture.
 */
class MediaPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    /** The site reads the profile in its header, on every page under test. */
    private function profile(User $owner): JournalistProfile
    {
        return JournalistProfile::create([
            'user_id' => $owner->id,
            'name' => 'Emrul Hasan Bappi',
            'title' => 'Staff Journalist',
            'short_bio' => 'Short.',
            'long_bio' => 'Long.',
            'skills' => [],
            'social_links' => [],
        ]);
    }

    /** A Media row with real bytes behind it, so existence checks pass. */
    private function image(User $owner, string $path, string $alt = 'An image'): Media
    {
        Storage::disk('public')->put($path, $this->jpegBytes());

        return Media::create([
            'type' => 'image',
            'file_path' => $path,
            'disk' => 'public',
            'original_filename' => basename($path),
            'alt_text' => $alt,
            'uploaded_by' => $owner->id,
        ]);
    }

    private function jpegBytes(): string
    {
        $image = imagecreatetruecolor(8, 8);
        ob_start();
        imagejpeg($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    /** A published internal piece, featured so the homepage draws its card. */
    private function piece(User $owner, string $slug, ?int $featuredImageId = null): ContentItem
    {
        return ContentItem::create([
            'author_id' => $owner->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'A piece with a picture',
            'slug' => $slug,
            'summary' => 'Summary.',
            'body' => '<p>Body.</p>',
            'featured_image_media_id' => $featuredImageId,
            'is_featured' => true,
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);
    }

    // ── Sitemap ─────────────────────────────────────────────────────────

    /**
     * `<image:loc>` must be absolute.
     *
     * Media URLs are root-relative on purpose (MediaUrlTest), and that is right
     * for an `<img src>`: a browser resolves it against the page. A sitemap has
     * no page — it is read by a crawler that resolves the string against
     * nothing — and the image extension requires an absolute URL. A relative
     * one is not an error the sitemap tool reports; the entry is dropped, so
     * every image this sitemap advertises goes unindexed.
     */
    public function test_sitemap_image_locations_are_absolute(): void
    {
        Storage::fake('public');

        $owner = $this->owner();
        $media = $this->image($owner, 'content-items/featured/sitemap-share.jpg');

        $this->piece($owner, 'a-piece-in-the-sitemap', $media->id);

        $content = $this->get('/sitemap.xml')->assertOk()->getContent();

        $xml = simplexml_load_string($content);
        $this->assertNotFalse($xml);

        $xml->registerXPathNamespace('image', 'http://www.google.com/schemas/sitemap-image/1.1');

        $imageLocs = $xml->xpath('//image:loc');

        $this->assertNotEmpty($imageLocs, 'the image extension must be emitted');
        $this->assertStringStartsWith(
            'http',
            (string) $imageLocs[0],
            'a root-relative <image:loc> is discarded by Google'
        );
        $this->assertStringContainsString('sitemap-share.jpg', (string) $imageLocs[0]);
    }

    // ── Adding to the media library ─────────────────────────────────────

    /**
     * The media library's create screen must actually be able to create a row.
     *
     * `uploaded_by` is NOT NULL and the form's field for it is `disabled()` —
     * and in Filament 5 a disabled field is excluded from dehydration, so the
     * column received nothing and every attempt to add a file through
     * Admin → Media failed on the foreign key. The page existed, looked
     * correct, and could never succeed.
     *
     * Driven through the upload path a browser takes rather than by filling the
     * FK with an id: the point is that an ordinary upload saves.
     */
    public function test_adding_a_file_to_the_media_library_records_the_uploader(): void
    {
        Storage::fake('public');

        $owner = $this->owner();
        $this->actingAs($owner);

        Livewire::test(CreateMedia::class)
            ->set('data.type', 'image')
            ->set('data.file_path', [UploadedFile::fake()->image('portrait.jpg', 600, 600)])
            ->set('data.original_filename', 'portrait.jpg')
            ->set('data.alt_text', 'A portrait')
            ->call('create')
            ->assertHasNoFormErrors();

        $media = Media::query()->where('original_filename', 'portrait.jpg')->first();

        $this->assertNotNull($media, 'the media library must be able to store an upload');
        $this->assertSame($owner->id, $media->uploaded_by);
        Storage::disk('public')->assertExists($media->file_path);
        $this->assertSame('A portrait', $media->alt_text);
    }

    // ── Cache invalidation ──────────────────────────────────────────────

    /**
     * Editing a Media row must invalidate the cached public page.
     *
     * A media row *is* public markup: its URL, alt text and caption are
     * rendered into pages that are cached whole for the TTL. Without Media on
     * the invalidation list, an alt-text fix, a replaced file, or a deleted
     * image left the cached page pointing at the old description — or at a file
     * that no longer exists, so the page served a broken image. Nothing logged,
     * nothing failed: the page simply kept serving the previous state.
     */
    public function test_editing_a_media_row_invalidates_the_cached_page(): void
    {
        Storage::fake('public');

        $owner = $this->owner();
        $this->profile($owner);

        $media = $this->image($owner, 'content-items/featured/cache-test.jpg', 'Cache alt before');
        $this->piece($owner, 'a-piece-in-the-cache', $media->id);

        $this->get('/')->assertOk()->assertSee('Cache alt before', false);
        $this->get('/')->assertHeader(CachePublicResponses::HEADER, CachePublicResponses::HIT);

        $versionBefore = PageCache::contentVersion();

        $media->update(['alt_text' => 'Cache alt after']);

        $this->assertGreaterThan(
            $versionBefore,
            PageCache::contentVersion(),
            'a Media edit must bump the page-cache version'
        );

        // The observable outcome: the very next request renders the new alt
        // text rather than replaying the description that is no longer true.
        $this->get('/')
            ->assertOk()
            ->assertSee('Cache alt after', false)
            ->assertDontSee('Cache alt before', false);
    }

    /**
     * An alt-text edit through the upload field must go through the model.
     *
     * MediaFileUpload's dehydrate step used to write the alt text back with a
     * query-builder `update()`, which fires no Eloquent events — so neither the
     * page cache nor the SECURITY.md §15 audit trail heard about a change that
     * every page showing that image renders.
     */
    public function test_the_upload_component_writes_alt_text_through_the_model(): void
    {
        Storage::fake('public');

        $owner = $this->owner();
        $this->actingAs($owner);

        $media = $this->image($owner, 'content-items/featured/alt-test.jpg', 'Original alt');
        $item = $this->piece($owner, 'a-piece-with-alt-text', $media->id);

        $versionBefore = PageCache::contentVersion();

        Livewire::test(EditContentItem::class, ['record' => $item->slug])
            ->set('data.featured_image_alt_text', 'Rewritten alt text')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Rewritten alt text', $media->refresh()->alt_text);
        $this->assertGreaterThan(
            $versionBefore,
            PageCache::contentVersion(),
            'an alt-text edit must invalidate cached markup that renders it'
        );
    }

    // ── Share cards ─────────────────────────────────────────────────────

    /**
     * `og:image:alt` must describe the image that was actually emitted.
     *
     * The site-wide alt was applied first, so a page with its own picture and
     * its own alt still advertised a sentence written for a different image —
     * and the page's own description could never win.
     */
    public function test_the_share_card_alt_matches_the_image_emitted(): void
    {
        Storage::fake('public');

        $owner = $this->owner();
        $this->profile($owner);

        // A configured default share card with its own alt text.
        Setting::updateOrCreate([
            'key' => 'default_og_image_media_id',
        ], ['value' => $this->image($owner, 'settings/og/default.jpg', 'Default card alt')->id]);
        Setting::updateOrCreate(['key' => 'default_og_image_alt'], ['value' => 'The site-wide share card']);
        SiteSettings::flush();

        $pieceImage = $this->image($owner, 'content-items/featured/piece.jpg', 'The piece its own alt');
        $item = $this->piece($owner, 'a-piece-with-its-own-picture', $pieceImage->id);

        $html = $this->get($item->publicPath())->assertOk()->getContent();

        $this->assertSame(
            1,
            preg_match('~<meta property="og:image" content="([^"]+)"~', $html, $og),
            'no og:image tag emitted'
        );
        $this->assertStringContainsString('piece.jpg', $og[1]);

        $this->assertSame(
            1,
            preg_match('~<meta property="og:image:alt" content="([^"]*)"~', $html, $alt),
            'no og:image:alt tag emitted'
        );
        $this->assertSame(
            'The piece its own alt',
            $alt[1],
            'og:image:alt must describe the emitted image, not the configured default'
        );

        // With no picture of its own the piece falls back to the default card,
        // and then the default's own alt text is the correct one.
        $plain = $this->piece($owner, 'a-piece-with-no-picture');

        $fallback = $this->get($plain->publicPath())->assertOk()->getContent();

        $this->assertSame(
            1,
            preg_match('~<meta property="og:image:alt" content="([^"]*)"~', $fallback, $fallbackAlt),
            'the fallback card must carry alt text too'
        );
        $this->assertSame('The site-wide share card', $fallbackAlt[1]);
    }
}
