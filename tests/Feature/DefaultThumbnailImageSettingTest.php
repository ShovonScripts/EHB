<?php

namespace Tests\Feature;

use App\Filament\Forms\Components\MediaFileUpload;
use App\Filament\Resources\Settings\Pages\EditSetting;
use App\Filament\Resources\Settings\Pages\ListSettings;
use App\Models\ContentItem;
use App\Models\JournalistProfile;
use App\Models\Media;
use App\Models\Setting;
use App\Models\User;
use App\Support\SeoSettings;
use App\Support\SiteSettings;
use Filament\Forms\Components\Textarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The site-wide default thumbnail for the cards in the work grid.
 *
 * One card component draws every listing on the site (DESIGN_SYSTEM.md §7), and
 * it only ever drew a picture when the piece had one of its own — so on a
 * link-out archive, where almost nothing has a body image, most cards were a
 * title and dek with a gap where the thumbnail belongs. This setting is the one
 * upload that fills those gaps.
 *
 * The failure modes worth an assertion, in the order they bite:
 *
 * 1. Declared but not editable — a preset entry with no database row or no
 *    picker, which `SeoSettingsPresetTest` names as the cost of adding a
 *    setting to the preset alone.
 * 2. Taking over cards that already have their own picture, so one config
 *    change silently rewrites every thumbnail on the site.
 * 3. Drawing a broken `<img>` when it is unset or points at a deleted Media
 *    row — the same mistake `DefaultOgImageSettingTest` exists for, one layer
 *    down: there it cost every share an image, here it would cost a grid its
 *    only picture.
 */
class DefaultThumbnailImageSettingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The owner, seeded once per test.
     *
     * Created before anything that references a user: `media.uploaded_by` and
     * `content_items.author_id` are foreign keys, and a row pointing at a user
     * that does not exist yet is an integrity violation rather than a soft
     * error. The profile rides along because the pages under test render it.
     */
    private ?User $owner = null;

    private function owner(): User
    {
        if ($this->owner !== null) {
            return $this->owner;
        }

        $this->owner = User::factory()->create(['role' => 'owner']);

        JournalistProfile::create([
            'user_id' => $this->owner->id,
            'name' => 'Emrul Hasan Bappi',
            'title' => 'Staff Journalist',
            'short_bio' => 'x',
            'long_bio' => 'y',
            'skills' => [],
            'social_links' => [],
        ]);

        return $this->owner;
    }

    /** A Media row for the default thumbnail, with the file behind it. */
    private function defaultThumbnail(): Media
    {
        Storage::fake('public');
        Storage::disk('public')->put('settings/cards/thumbnail.webp', 'img');

        return Media::create([
            'type' => 'image',
            'file_path' => 'settings/cards/thumbnail.webp',
            'disk' => 'public',
            'original_filename' => 'thumbnail.webp',
            'alt_text' => 'A card thumbnail.',
            'uploaded_by' => $this->owner()->id,
        ]);
    }

    private function setDefault(mixed $value): void
    {
        Setting::updateOrCreate(['key' => 'default_card_image_media_id'], ['value' => $value]);
        SiteSettings::flush();
    }

    /**
     * A published, featured piece, carrying an image of its own only when one
     * is given.
     *
     * Featured because that is where the homepage draws its cards from: its
     * "latest" slot uses the imageless `content-row` layout, so a piece that
     * is not featured is never rendered by `content-card.blade.php` at all and
     * a test about cards would assert nothing. The profile rides along for the
     * same reason — the header on every page under test renders it.
     */
    private function piece(?int $featuredImageId = null): ContentItem
    {
        Storage::fake('public');

        return ContentItem::create([
            'author_id' => $this->owner()->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'A piece in the grid',
            'slug' => 'piece-'.uniqid(),
            'summary' => 'Summary.',
            'body' => '<p>Body.</p>',
            'featured_image_media_id' => $featuredImageId,
            'is_featured' => true,
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    // ── The setting ──────────────────────────────────────────────────────

    /** The preset is the whole source of truth for how a setting is edited. */
    public function test_the_preset_declares_this_key_as_an_image(): void
    {
        $this->assertSame('image', SeoSettings::typeOf('default_card_image_media_id'));
    }

    public function test_the_standalone_edit_page_offers_an_upload_for_it(): void
    {
        $this->actingAs($this->owner());
        $setting = Setting::updateOrCreate(['key' => 'default_card_image_media_id'], ['value' => null]);

        $form = Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
            ->assertSuccessful()
            ->instance()
            ->form;

        $classes = array_map('get_class', $form->getComponents());
        $this->assertContains(MediaFileUpload::class, $classes);
        $this->assertNotContains(
            Textarea::class,
            $classes,
            'A media reference edited as free text is how a raw id gets mistyped.'
        );
    }

    /**
     * The list is where settings are edited day to day, so the modal there has
     * to offer the upload too — and say which setting it uploads for.
     */
    public function test_the_list_edit_modal_offers_an_upload_labelled_with_its_name(): void
    {
        $this->actingAs($this->owner());
        $setting = Setting::updateOrCreate(['key' => 'default_card_image_media_id'], ['value' => null]);

        $modal = Livewire::test(ListSettings::class)
            ->mountTableAction('edit', (string) $setting->getKey());

        $modal->assertMountedActionModalSee('Default thumbnail image');

        $classes = array_map(
            'get_class',
            $modal->instance()->getMountedTableActionForm()->getComponents()
        );

        $this->assertContains(MediaFileUpload::class, $classes);
    }

    /**
     * End to end: pick a file in the browser, save, and the stored value must
     * be a Media row the card component can render — in the thumbnail's own
     * folder, so the two image settings do not pile into one directory.
     */
    public function test_uploading_a_file_stores_a_resolvable_media_id(): void
    {
        Storage::fake('public');
        $this->actingAs($this->owner());
        $setting = Setting::updateOrCreate(['key' => 'default_card_image_media_id'], ['value' => null]);

        Livewire::test(ListSettings::class)
            ->mountTableAction('edit', (string) $setting->getKey())
            ->setTableActionData(['value' => [UploadedFile::fake()->image('thumb.jpg', 1200, 900)]])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $setting->refresh();
        SiteSettings::flush();

        $this->assertNotNull($setting->value, 'The upload should have been stored as a media reference.');

        $media = Media::find((int) $setting->value);
        $this->assertNotNull($media, 'The stored value must resolve to a Media row, not a path or a stray id.');
        $this->assertSame($media->url, SiteSettings::defaultCardImageUrl());
        Storage::disk('public')->assertExists($media->file_path);
        $this->assertStringStartsWith(
            'settings/cards/',
            $media->file_path,
            'The thumbnail belongs in its own folder, not alongside the share images.'
        );
    }

    // ── The fallback ─────────────────────────────────────────────────────

    public function test_a_card_without_its_own_image_uses_the_default_thumbnail(): void
    {
        $media = $this->defaultThumbnail();
        $this->setDefault($media->id);
        $this->piece();

        $this->get('/')
            ->assertOk()
            ->assertSee('content-card-thumb', false)
            ->assertSee($media->url, false)
            ->assertSee('alt="A card thumbnail."', false);
    }

    /** One config setting must not rewrite the pictures pieces already have. */
    public function test_a_piece_with_its_own_image_keeps_it(): void
    {
        $default = $this->defaultThumbnail();
        $this->setDefault($default->id);

        Storage::disk('public')->put('content/items/piece.webp', 'img');
        $own = Media::create([
            'type' => 'image',
            'file_path' => 'content/items/piece.webp',
            'disk' => 'public',
            'original_filename' => 'piece.webp',
            'alt_text' => "This piece's own picture.",
            'uploaded_by' => $this->owner()->id,
        ]);
        $this->piece($own->id);

        $this->get('/')
            ->assertOk()
            ->assertSee($own->url, false)
            ->assertDontSee($default->url, false);
    }

    /**
     * Nothing configured, and nothing changes: cards stay pictureless rather
     * than rendering an empty <img> for the browser to show as a broken icon.
     */
    public function test_with_nothing_configured_no_picture_is_rendered(): void
    {
        $this->setDefault(null);
        $this->piece();

        $this->get('/')
            ->assertOk()
            ->assertDontSee('content-card-thumb', false);
    }

    /** A stale id costs the card its image; it never costs the page a 500. */
    public function test_a_stale_media_id_renders_the_card_without_a_picture(): void
    {
        $this->setDefault(999999);
        $this->piece();

        $this->get('/')
            ->assertOk()
            ->assertDontSee('content-card-thumb', false);
    }
}
