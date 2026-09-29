<?php

namespace Tests\Feature;

use App\Filament\Forms\Components\MediaFileUpload;
use App\Filament\Resources\Settings\Pages\EditSetting;
use App\Models\JournalistProfile;
use App\Models\Media;
use App\Models\Setting;
use App\Models\User;
use App\Support\SeoSettings;
use App\Support\SiteSettings;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The site-wide default social share image.
 *
 * Two defects, both of which cost every share on the site its image while
 * showing nothing in the admin:
 *
 * 1. `defaultOgImageUrl()` returned null when a configured media id did not
 *    resolve, skipping the portrait fallback. The layout omits `og:image`
 *    entirely for null, so one stale id stripped the image from all 210
 *    pieces' social cards.
 * 2. The setting was edited as a bare text box, so the only way to set it was
 *    to type a raw numeric media id — which is how (1) gets triggered.
 */
class DefaultOgImageSettingTest extends TestCase
{
    use RefreshDatabase;

    private function seedOwnerAndPortrait(): User
    {
        Storage::fake('public');
        Storage::disk('public')->put('journalist/profile/portrait.webp', 'img');

        $owner = User::factory()->create(['role' => 'owner']);

        // A real Media row, actually linked to the profile. Without this
        // `portraitUrl()` is null and every fallback assertion below compares
        // null to null — passing while proving nothing.
        $portrait = Media::create([
            'type' => 'image',
            'file_path' => 'journalist/profile/portrait.webp',
            'disk' => 'public',
            'original_filename' => 'portrait.webp',
            'alt_text' => 'Portrait of the journalist.',
            'uploaded_by' => 1,
        ]);

        JournalistProfile::create([
            'user_id' => $owner->id,
            'name' => 'Emrul Hasan Bappi',
            'title' => 'Staff Journalist',
            'photo_media_id' => $portrait->id,
            'short_bio' => 'x',
            'long_bio' => 'y',
            'skills' => [],
            'social_links' => [],
        ]);

        return $owner;
    }

    private function seedMedia(string $path = 'settings/og/card.webp'): Media
    {
        Storage::disk('public')->put($path, 'img');

        return Media::create([
            'type' => 'image',
            'file_path' => $path,
            'disk' => 'public',
            'original_filename' => basename($path),
            'alt_text' => 'A share card.',
            'uploaded_by' => 1,
        ]);
    }

    private function setDefault(mixed $value): void
    {
        Setting::updateOrCreate(['key' => 'default_og_image_media_id'], ['value' => $value]);
        SiteSettings::flush();
    }

    // ── The fallback ───────────────────────────────────────────────

    public function test_an_unset_default_falls_back_to_the_portrait(): void
    {
        $this->seedOwnerAndPortrait();
        $this->setDefault(null);

        $this->assertSame(
            SiteSettings::portraitUrl(),
            SiteSettings::defaultOgImageUrl()
        );
    }

    public function test_a_configured_media_row_wins(): void
    {
        $this->seedOwnerAndPortrait();
        $media = $this->seedMedia();
        $this->setDefault($media->id);

        $this->assertSame($media->url, SiteSettings::defaultOgImageUrl());
    }

    /**
     * The regression. A stale or mistyped id must degrade to the portrait, not
     * to null — null means the layout emits no `og:image` at all, so the cost
     * of one bad value was every social card on the site.
     */
    public function test_an_unresolvable_id_falls_back_to_the_portrait(): void
    {
        $this->seedOwnerAndPortrait();
        $this->setDefault(999999);

        $this->assertSame(
            SiteSettings::portraitUrl(),
            SiteSettings::defaultOgImageUrl(),
            'A bad media id must not strip og:image from every page.'
        );
    }

    /** A deleted Media row is the realistic way to end up with a stale id. */
    public function test_a_deleted_media_row_falls_back_to_the_portrait(): void
    {
        $this->seedOwnerAndPortrait();
        $media = $this->seedMedia();
        $this->setDefault($media->id);

        $this->assertSame($media->url, SiteSettings::defaultOgImageUrl());

        $media->delete();
        SiteSettings::flush();

        $this->assertSame(SiteSettings::portraitUrl(), SiteSettings::defaultOgImageUrl());
    }

    /** null is still correct when there is genuinely nothing to fall back to. */
    public function test_null_only_when_there_is_no_portrait_and_no_usable_default(): void
    {
        Storage::fake('public');
        User::factory()->create(['role' => 'owner']);
        $this->setDefault(999999);

        $this->assertNull(SiteSettings::defaultOgImageUrl());
    }

    /** The public page must still emit an og:image tag in every usable case. */
    public function test_the_page_still_emits_an_og_image_tag_when_the_id_is_bad(): void
    {
        $this->seedOwnerAndPortrait();
        $this->setDefault(999999);

        $this->get('/')
            ->assertOk()
            ->assertSee('property="og:image"', false);
    }

    // ── The editor ─────────────────────────────────────────────────

    /**
     * The preset knows this key takes an image.
     *
     * The form is generated from the preset, so this is where "which settings
     * are images" is decided. It used to live on the form as `MEDIA_KEYS`.
     */
    public function test_the_preset_declares_this_key_as_an_image(): void
    {
        $this->assertSame('image', SeoSettings::typeOf('default_og_image_media_id'));
    }

    /**
     * The picker replaces the text box for media keys, so the raw-id
     * mistype is no longer reachable through the UI.
     *
     * Asserted on the built schema rather than scraped from the HTML: the
     * component list is the precise answer.
     */
    public function test_the_image_picker_replaces_the_text_box_for_media_keys(): void
    {
        $owner = $this->seedOwnerAndPortrait();
        $this->actingAs($owner);

        $setting = Setting::updateOrCreate(['key' => 'default_og_image_media_id'], ['value' => null]);

        $form = Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
            ->assertSuccessful()
            ->instance()
            ->form;

        $classes = array_map('get_class', $form->getComponents());

        $this->assertContains(MediaFileUpload::class, $classes);
        $this->assertNotContains(
            Textarea::class,
            $classes,
            'The free-text value box must not be offered for a media reference.'
        );
    }

    /** A non-image setting gets an ordinary text control, not a picker. */
    public function test_other_settings_keep_a_plain_text_value(): void
    {
        $owner = $this->seedOwnerAndPortrait();
        $this->actingAs($owner);

        $setting = Setting::updateOrCreate(['key' => 'site_tagline'], ['value' => 'A tagline']);

        $form = Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
            ->assertSuccessful()
            ->instance()
            ->form;

        $classes = array_map('get_class', $form->getComponents());

        $this->assertContains(TextInput::class, $classes);
        $this->assertNotContains(MediaFileUpload::class, $classes);
    }

    /**
     * End to end: pick a file in the browser, save, and the stored value must
     * be a media id that `SiteSettings` can resolve.
     *
     * Simulated with a real fake upload rather than `fillForm` with an id,
     * because FileUpload's state is an array of files — a scalar id is not a
     * value the field can hold, and Filament rejects one as a tampered path.
     * Filling it with an id would test nothing about the upload path this
     * field exists to provide.
     */
    public function test_uploading_an_image_stores_a_resolvable_media_id(): void
    {
        $owner = $this->seedOwnerAndPortrait();
        $this->actingAs($owner);

        $setting = Setting::updateOrCreate(['key' => 'default_og_image_media_id'], ['value' => null]);

        $upload = UploadedFile::fake()->image('card.jpg', 1200, 630);

        Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
            ->set('data.value', [$upload])
            ->call('save')
            ->assertHasNoFormErrors();

        $setting->refresh();
        SiteSettings::flush();

        $this->assertNotNull($setting->value, 'The upload should have been stored as a media reference.');

        $media = Media::find((int) $setting->value);

        $this->assertNotNull($media, 'The stored value must resolve to a Media row.');
        $this->assertSame($media->url, SiteSettings::defaultOgImageUrl());

        Storage::disk('public')->assertExists($media->file_path);
    }

    /**
     * The form still exposes the key, so a row can be renamed.
     *
     * The earlier version of this form swapped the value control based on a
     * live `key` field, which meant renaming a setting mid-edit silently
     * changed which control was on screen. The form is now built from the
     * record's key instead, so the key is an ordinary field and the control
     * cannot change underneath the person editing.
     */
    public function test_the_key_field_is_editable(): void
    {
        $owner = $this->seedOwnerAndPortrait();
        $this->actingAs($owner);

        $setting = Setting::updateOrCreate(['key' => 'default_og_image_media_id'], ['value' => null]);

        $form = Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
            ->assertSuccessful()
            ->instance()
            ->form;

        $names = array_map(
            fn ($c) => method_exists($c, 'getName') ? $c->getName() : null,
            $form->getComponents()
        );

        $this->assertContains('key', array_filter($names));
    }
}
