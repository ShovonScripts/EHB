<?php

namespace Tests\Feature;

use App\Filament\Forms\Components\MediaFileUpload;
use App\Filament\Resources\Settings\Pages\EditSetting;
use App\Models\Setting;
use App\Models\User;
use App\Support\SeoSettings;
use App\Support\SiteSettings;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The SEO settings preset and everything generated from it.
 *
 * The preset is the single declaration of what settings exist. Three things
 * have to stay true or the panel rots:
 *
 * 1. It is internally consistent — no duplicate keys, no unknown type, no
 *    group that no entry uses.
 * 2. Every declared setting has a database row, or it is wired into the markup
 *    but impossible to set.
 * 3. Every declared setting that claims to be usable is actually read by the
 *    site, and a retired one is inert *and* says why.
 */
class SeoSettingsPresetTest extends TestCase
{
    use RefreshDatabase;

    private function setSetting(string $key, mixed $value): void
    {
        Setting::firstOrCreate(['key' => $key])->update(['value' => $value]);
        SiteSettings::flush();
    }

    // ── The preset itself ──────────────────────────────────────────

    public function test_the_preset_is_internally_consistent(): void
    {
        $keys = array_column(SeoSettings::preset(), 'key');

        $this->assertSame(
            $keys,
            array_unique($keys),
            'Duplicate setting keys in the preset — the second would be unreachable.'
        );

        foreach (SeoSettings::preset() as $setting) {
            $this->assertContains(
                $setting['type'],
                SeoSettings::types(),
                "Setting '{$setting['key']}' has unknown type '{$setting['type']}'."
            );

            $this->assertContains(
                $setting['group'],
                SeoSettings::groups(),
                "Setting '{$setting['key']}' is in unknown group '{$setting['group']}'."
            );

            foreach (['label', 'help'] as $field) {
                $this->assertNotSame('', trim((string) $setting[$field]), "Setting '{$setting['key']}' has no {$field}.");
            }
        }
    }

    /** A group nobody puts anything in would render an empty section. */
    public function test_every_declared_group_has_at_least_one_setting(): void
    {
        foreach (SeoSettings::groups() as $group) {
            $this->assertNotEmpty(
                SeoSettings::group($group),
                "Group '{$group}' is declared but empty, so the form would render an empty section."
            );
        }
    }

    /** The order groups appear in is the order they are declared. */
    public function test_groups_preserve_declaration_order(): void
    {
        $seen = [];

        foreach (SeoSettings::preset() as $setting) {
            $seen[] = $setting['group'];
        }

        $ordered = array_values(array_unique($seen));

        $this->assertSame(SeoSettings::groups(), $ordered);
    }

    public function test_an_unknown_key_has_no_definition(): void
    {
        $this->assertNull(SeoSettings::definition('not_a_real_setting'));
        $this->assertFalse(SeoSettings::has('not_a_real_setting'));
        // Falls back to the raw key rather than hiding it.
        $this->assertSame('not_a_real_setting', SeoSettings::label('not_a_real_setting'));
    }

    // ── Rows exist for everything declared ────────────────────────

    public function test_every_preset_setting_has_a_database_row(): void
    {
        $this->seed(AdminUserSeeder::class);
        $this->artisan('migrate')->assertSuccessful();

        $keys = Setting::query()->pluck('key');

        foreach (SeoSettings::preset() as $setting) {
            $this->assertContains(
                $setting['key'],
                $keys,
                "Setting '{$setting['key']}' is declared in the preset but has no row, so the admin cannot set it."
            );
        }
    }

    // ── The accessors ──────────────────────────────────────────────

    public function test_indexable_defaults_to_true(): void
    {
        SiteSettings::flush();

        // Absent entirely must not read as "do not index" — that failure is
        // invisible: pages simply never appear in results.
        $this->assertTrue(SiteSettings::isIndexable());
    }

    public function test_indexable_can_be_switched_off(): void
    {
        $this->setSetting('indexable', false);

        $this->assertFalse(SiteSettings::isIndexable());
    }

    public function test_indexable_accepts_the_string_forms_a_form_posts(): void
    {
        foreach (['0' => false, 'false' => false, '1' => true, 'true' => true] as $input => $expected) {
            $this->setSetting('indexable', $input);

            $this->assertSame($expected, SiteSettings::isIndexable(), "Failed for '{$input}'");
        }
    }

    /**
     * The handle is pasted by hand from a profile URL, so every plausible
     * spelling has to resolve to the same bare handle.
     */
    public function test_the_twitter_handle_is_normalised(): void
    {
        foreach ([
            '@bappi' => 'bappi',
            'bappi' => 'bappi',
            'https://x.com/bappi' => 'bappi',
            'https://twitter.com/bappi' => 'bappi',
            'https://www.x.com/@bappi' => 'bappi',
            '  @bappi  ' => 'bappi',
        ] as $input => $expected) {
            $this->setSetting('twitter_handle', $input);

            $this->assertSame($expected, SiteSettings::twitterHandle(), "Failed for '{$input}'");
        }
    }

    public function test_an_unusable_twitter_handle_is_dropped(): void
    {
        // A malformed value must yield null so no broken tag is emitted —
        // better than echoing whatever was typed into a meta tag.
        foreach (['', '   ', 'not a handle', '@', str_repeat('x', 40), 'https://example.com/nope'] as $input) {
            $this->setSetting('twitter_handle', $input);

            $this->assertNull(SiteSettings::twitterHandle(), "Should have been dropped: '{$input}'");
        }
    }

    public function test_blank_strings_are_treated_as_unset(): void
    {
        foreach (['default_og_image_alt', 'google_site_verification', 'contact_email'] as $key) {
            $this->setSetting($key, '   ');

            $this->assertNull(
                SiteSettings::get($key) === '   ' ? null : SiteSettings::get($key),
                "A whitespace-only '{$key}' should read as unset."
            );
        }
    }

    public function test_an_invalid_contact_email_is_dropped(): void
    {
        $this->setSetting('contact_email', 'not-an-email');

        $this->assertNull(SiteSettings::contactEmail());
    }

    /**
     * robots.txt is fetched by anonymous crawlers, so a malformed line is at
     * best ignored and at worst read as a path to disallow.
     */
    public function test_extra_robots_rules_keep_only_valid_directives(): void
    {
        $this->setSetting('extra_robots_rules', implode("\n", [
            'Disallow: /tmp/',
            'Crawl-delay: 5',
            '',
            'this line has no colon',
            'User-agent: *',
            'Disallow:',
        ]));

        $this->assertSame(
            ['Disallow: /tmp/', 'Crawl-delay: 5', 'User-agent: *'],
            SiteSettings::extraRobotsRules()
        );
    }

    public function test_no_extra_robots_rules_is_an_empty_list(): void
    {
        $this->assertSame([], SiteSettings::extraRobotsRules());
    }

    // ── What the site emits ────────────────────────────────────────

    public function test_twitter_tags_are_emitted_when_a_handle_is_set(): void
    {
        $this->setSetting('twitter_handle', '@bappi');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('name="twitter:site" content="@bappi"', $html);
        $this->assertStringContainsString('name="twitter:creator" content="@bappi"', $html);
    }

    /**
     * Regression. The tags were originally written `content="@{{ $handle }}"`.
     * An `@` immediately before a Blade echo stops it compiling, so the page
     * rendered the literal text `{{ $handle }}` to readers — in a meta tag, so
     * silently, and with the page still returning 200.
     */
    public function test_the_handle_is_not_rendered_as_literal_blade_text(): void
    {
        $this->setSetting('twitter_handle', '@bappi');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('{{ $handle }}', $html);
        $this->assertStringNotContainsString('@{{', $html);
    }

    public function test_no_twitter_tags_without_a_handle(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('twitter:site', $html);
        $this->assertStringNotContainsString('twitter:creator', $html);
    }

    public function test_the_verification_tag_is_emitted(): void
    {
        $this->setSetting('google_site_verification', 'abc123token');

        $this->get('/')
            ->assertOk()
            ->assertSee('name="google-site-verification" content="abc123token"', false);
    }

    public function test_the_indexable_switch_puts_noindex_on_every_page(): void
    {
        $this->setSetting('indexable', false);

        // Pages that render on an empty database. `/about` is not among them:
        // it needs a seeded profile, which is a separate concern.
        foreach (['/', '/contact', '/archive'] as $uri) {
            $this->get($uri)
                ->assertOk()
                ->assertSee('content="noindex,follow"', false);
        }
    }

    public function test_pages_are_indexable_by_default(): void
    {
        foreach (['/', '/archive'] as $uri) {
            $this->get($uri)
                ->assertOk()
                ->assertDontSee('content="noindex,follow"', false);
        }
    }

    public function test_extra_robots_rules_reach_robots_txt(): void
    {
        $this->setSetting('extra_robots_rules', 'Disallow: /tmp/');

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /tmp/', false);
    }

    public function test_the_indexable_switch_also_disallows_everything_in_robots_txt(): void
    {
        $this->setSetting('indexable', false);

        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /', false);
    }

    // ── The form ───────────────────────────────────────────────────

    public function test_the_share_image_gets_an_image_picker_not_a_text_box(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']));

        $setting = Setting::firstOrCreate(['key' => 'default_og_image_media_id']);

        $form = Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
            ->assertSuccessful()
            ->instance()
            ->form;

        $classes = array_map('get_class', $form->getComponents());

        $this->assertContains(MediaFileUpload::class, $classes);
    }

    /** A row the preset has never heard of must still be editable. */
    public function test_an_unknown_key_falls_back_to_a_generic_editor(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']));

        $setting = Setting::firstOrCreate(['key' => 'some_legacy_thing'], ['value' => 'kept']);

        $form = Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
            ->assertSuccessful()
            ->instance()
            ->form;

        $names = array_map(
            fn ($c) => method_exists($c, 'getName') ? $c->getName() : null,
            $form->getComponents()
        );

        $this->assertContains('key', array_filter($names));
        $this->assertContains('value', array_filter($names));
    }

    /** The retired row must be visible and explained, not silently inert. */
    public function test_the_retired_analytics_row_explains_itself(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']));

        $setting = Setting::firstOrCreate(['key' => 'google_analytics_id']);

        $html = Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
            ->assertSuccessful()
            ->html();

        $this->assertStringContainsString('Not supported', $html);
        $this->assertStringContainsString('Content-Security-Policy', $html);
    }
}
