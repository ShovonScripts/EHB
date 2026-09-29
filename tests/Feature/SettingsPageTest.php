<?php

namespace Tests\Feature;

use App\Filament\Forms\Components\MediaFileUpload;
use App\Filament\Resources\Settings\Pages\ListSettings;
use App\Models\Media;
use App\Models\Setting;
use App\Models\User;
use App\Support\SeoSettings;
use App\Support\SiteSettings;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * /admin/settings — the screen the global settings are actually edited from.
 *
 * The list is the settings screen: one row per declared setting, edited in a
 * modal without leaving the page. Three things were wrong with it, and each is
 * asserted here because each was invisible in a screenshot:
 *
 * 1. The modal was built from the resource's form, which has no record and so
 *    could only produce the old two-field Key/Value editor. Every setting on
 *    the screen the journalist works from was a bare text box — the share image
 *    included, which is how a raw media id got typed in and every share on the
 *    site lost its image with nothing in the panel saying why.
 * 2. The table paginated at ten rows, so any setting past the tenth sat on a
 *    second page — when the preset declared eleven, it hid "X / Twitter
 *    handle". A hidden setting and a missing setting look identical to the
 *    person editing the site.
 * 3. Validation lived on the standalone edit page rather than on the control,
 *    so the modal accepted values the page refused.
 *
 * The standalone page (`EditSetting`) keeps its own coverage in
 * `DefaultOgImageSettingTest` and `SeoSettingsPresetTest`; this file is about
 * the list, which is where the settings are read and changed day to day.
 */
class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']));
    }

    /**
     * The row for one key.
     *
     * Goes through the model so writes are cast the same way the panel's are:
     * `value` is a JSON column, and a raw query-builder write would store an
     * unquoted string that reads back as null.
     */
    private function setting(string $key): Setting
    {
        return Setting::firstOrCreate(['key' => $key]);
    }

    /** The list page's edit modal, mounted for one setting key. */
    private function openModal(string $key): Testable
    {
        $setting = $this->setting($key);

        return Livewire::test(ListSettings::class)
            ->mountTableAction('edit', (string) $setting->getKey());
    }

    private function modalSchema(string $key): Schema
    {
        return $this->openModal($key)->instance()->getMountedTableActionForm();
    }

    /**
     * The control the modal actually edits the value with.
     *
     * Asserting on the component *named* `value` rather than on the list of
     * components is the difference between a real assertion and a vacuous one:
     * every form carries a `key` text input, so "the modal contains a TextInput"
     * is true for every declared setting, the image ones included.
     */
    private function valueControlClass(string $key): string
    {
        foreach ($this->modalSchema($key)->getComponents() as $component) {
            if (method_exists($component, 'getName') && $component->getName() === 'value') {
                return $component::class;
            }
        }

        $this->fail("The edit modal for '{$key}' has no `value` control.");
    }

    // ── The list ───────────────────────────────────────────────────

    public function test_the_page_shows_every_declared_setting(): void
    {
        $this->owner();

        $html = Livewire::test(ListSettings::class)
            ->assertSuccessful()
            ->html();

        foreach (SeoSettings::preset() as $setting) {
            $this->assertStringContainsString(
                e($setting['label']),
                $html,
                "Setting '{$setting['key']}' is not on the settings screen."
            );
        }
    }

    public function test_the_page_loads_over_http(): void
    {
        $this->owner();

        $this->get('/admin/settings')
            ->assertOk()
            ->assertSee('Default share image');
    }

    public function test_all_settings_are_on_one_page_so_none_can_hide_behind_a_page_control(): void
    {
        $this->owner();

        $component = Livewire::test(ListSettings::class)->assertSuccessful();

        $this->assertFalse(
            $component->instance()->getTable()->isPaginated(),
            'The settings list paginated: a setting on page two is a setting nobody finds. '
            .'The table paged at ten rows and the preset declares '.count(SeoSettings::preset()).'.'
        );

        $this->assertSame(
            count(SeoSettings::preset()),
            $component->instance()->getTableRecords()->count(),
            'Every declared setting should be on the first and only page.'
        );
    }

    /** The preset's declared order is the order the panel is meant to show. */
    public function test_rows_are_ordered_the_way_the_preset_declares_them(): void
    {
        $this->owner();

        // Named to sort *first* alphabetically, so it can only land last if the
        // order really comes from the preset rather than from the key.
        $legacy = Setting::create(['key' => 'aaa_legacy_thing', 'value' => 'kept']);

        $keys = Livewire::test(ListSettings::class)
            ->instance()
            ->getTableRecords()
            ->pluck('key')
            ->all();

        $this->assertSame(
            array_column(SeoSettings::preset(), 'key'),
            array_slice($keys, 0, count(SeoSettings::preset())),
            'The list should read in the preset order: identity, social, cards, search, contact.'
        );

        $this->assertSame(
            $legacy->key,
            end($keys),
            'A row the preset does not know belongs at the end, not mixed in with the live settings.'
        );
    }

    public function test_the_group_filter_narrows_the_list_to_one_group(): void
    {
        $this->owner();

        $social = Setting::query()
            ->whereIn('key', array_column(SeoSettings::group(SeoSettings::GROUP_SOCIAL), 'key'))
            ->get();

        $others = Setting::query()
            ->whereNotIn('key', $social->pluck('key'))
            ->get();

        Livewire::test(ListSettings::class)
            ->filterTable('setting_group', SeoSettings::GROUP_SOCIAL)
            ->assertCanSeeTableRecords($social)
            ->assertCanNotSeeTableRecords($others);
    }

    /** A legacy row is in none of the preset's groups, and must not be hidden by one. */
    public function test_the_group_filter_has_a_bucket_for_rows_the_preset_does_not_know(): void
    {
        $this->owner();

        $legacy = Setting::create(['key' => 'legacy_thing', 'value' => 'kept']);

        $this->assertSame(SeoSettings::GROUP_UNKNOWN, $legacy->setting_group);

        Livewire::test(ListSettings::class)
            ->filterTable('setting_group', SeoSettings::GROUP_UNKNOWN)
            ->assertCanSeeTableRecords([$legacy])
            ->assertCanNotSeeTableRecords(
                Setting::whereIn('key', array_column(SeoSettings::preset(), 'key'))->get()
            );
    }

    public function test_a_row_the_preset_does_not_know_is_still_listed_and_editable(): void
    {
        $this->owner();

        $legacy = Setting::create(['key' => 'legacy_thing', 'value' => 'kept']);

        // Listed under its raw key — visible as an anomaly rather than hidden.
        Livewire::test(ListSettings::class)->assertCanSeeTableRecords([$legacy]);

        $names = array_map(
            fn ($component) => method_exists($component, 'getName') ? $component->getName() : null,
            $this->modalSchema('legacy_thing')->getComponents()
        );

        $this->assertContains('key', array_filter($names));
        $this->assertContains('value', array_filter($names));
    }

    // ── The modal's controls ───────────────────────────────────────

    public function test_every_setting_is_edited_with_the_control_its_type_calls_for(): void
    {
        $this->owner();

        // The control each declared type must produce. `text` is spelled out
        // rather than treated as "anything else" because a text box is the
        // correct answer for exactly one of these types.
        $expected = [
            'text' => TextInput::class,
            'textarea' => Textarea::class,
            'image' => MediaFileUpload::class,
            'toggle' => Toggle::class,
            'email' => TextInput::class,
            'handle' => TextInput::class,
            'retired' => TextInput::class,
        ];

        foreach (SeoSettings::preset() as $setting) {
            $this->assertArrayHasKey(
                $setting['type'],
                $expected,
                "Setting '{$setting['key']}' has type '{$setting['type']}', which this test cannot express."
            );

            $this->assertSame(
                $expected[$setting['type']],
                $this->valueControlClass($setting['key']),
                "Setting '{$setting['key']}' (type {$setting['type']}) is edited with the wrong control."
            );
        }
    }

    public function test_the_share_image_is_chosen_from_the_list_not_typed_in_as_a_media_id(): void
    {
        Storage::fake('public');
        $this->owner();

        $setting = $this->setting('default_og_image_media_id');
        $setting->update(['value' => null]);
        SiteSettings::flush();

        $modal = $this->openModal('default_og_image_media_id');

        $this->assertSame(MediaFileUpload::class, $this->valueControlClass('default_og_image_media_id'));

        // Labelled with the preset's name for it, so the field says what it
        // configures instead of showing a bare "Value" box.
        $modal->assertMountedActionModalSee('Default share image');

        $modal
            ->setTableActionData(['value' => [UploadedFile::fake()->image('card.jpg', 1200, 630)]])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $setting->refresh();
        SiteSettings::flush();

        $this->assertNotNull($setting->value, 'The upload should have been stored as a media reference.');

        $media = Media::find((int) $setting->value);

        $this->assertNotNull($media, 'The stored value must resolve to a Media row, not a path or a stray id.');
        $this->assertSame($media->url, SiteSettings::defaultOgImageUrl());
        Storage::disk('public')->assertExists($media->file_path);
    }

    public function test_the_modal_is_titled_with_the_setting_rather_than_its_key(): void
    {
        $this->owner();

        $this->assertSame(
            'Edit Default share image',
            $this->openModal('default_og_image_media_id')->instance()->getMountedAction()->getModalHeading()
        );
    }

    // ── The modal's validation and saving ──────────────────────────

    public function test_the_modal_validates_the_value_from_its_declared_type(): void
    {
        $this->owner();

        $this->setting('contact_email')->update(['value' => 'before@example.com']);
        SiteSettings::flush();

        $this->openModal('contact_email')
            ->setTableActionData(['value' => 'not-an-email'])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['value']);

        $this->assertSame(
            'before@example.com',
            $this->setting('contact_email')->value,
            'A value the modal rejected must not have been written.'
        );

        // The length rule for a text setting travels with the control too.
        $this->openModal('site_name')
            ->setTableActionData(['value' => str_repeat('a', 300)])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['value']);
    }

    public function test_switching_indexing_off_from_the_list_takes_effect(): void
    {
        $this->owner();

        $this->openModal('indexable')
            ->setTableActionData(['value' => false])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        SiteSettings::flush();

        $this->assertFalse(SiteSettings::isIndexable());

        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /', false);
    }
}
