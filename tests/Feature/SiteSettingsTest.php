<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\ContentItem;
use App\Models\JournalistProfile;
use App\Models\Media;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-406 / FR-404 — the site-wide settings the journalist edits in the admin
 * panel must actually change the public site, and admin-authored CMS pages
 * must be reachable (DATABASE.md §97).
 */
class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function seedProfile(): JournalistProfile
    {
        $owner = User::factory()->create(['role' => 'owner']);

        return JournalistProfile::create([
            'user_id' => $owner->id,
            'name' => 'Test Journalist',
            'title' => 'Staff Writer',
            'short_bio' => 'Short bio.',
            'long_bio' => 'Long bio.',
        ]);
    }

    // ── Settings reach the public site ───────────────────────────

    public function test_site_name_setting_is_used_for_title_and_metadata(): void
    {
        $this->seedProfile();
        Setting::updateOrCreate(['key' => 'site_name'], ['value' => 'The Bay Ledger']);

        $content = $this->get('/')->content();

        // The homepage supplies its own title; the setting is the site-name
        // suffix, and the whole site identity comes from Settings.
        $this->assertStringContainsString('<title>', $content);
        $this->assertStringContainsString('| The Bay Ledger</title>', $content);
        $this->assertStringContainsString('og:site_name" content="The Bay Ledger"', $content);
        $this->assertStringContainsString('rel="alternate" type="application/rss+xml" title="The Bay Ledger"', $content);
    }

    public function test_site_name_is_used_where_no_page_title_is_supplied(): void
    {
        $this->seedProfile();
        Setting::updateOrCreate(['key' => 'site_name'], ['value' => 'The Bay Ledger']);

        // The 404 view passes no title, so the site name becomes the title.
        $content = $this->get('/no-such-route-at-all')->content();

        $this->assertStringContainsString('The Bay Ledger', $content);
    }

    public function test_site_name_change_is_visible_immediately(): void
    {
        $this->seedProfile();
        $setting = Setting::updateOrCreate(['key' => 'site_name'], ['value' => 'First Title']);

        $this->get('/');
        $this->assertStringContainsString('| First Title</title>', $this->get('/')->content());

        // Saving through the model (what the admin panel does) must bust both
        // the settings cache and the public page cache.
        $setting->update(['value' => 'Second Title']);

        $second = $this->get('/')->content();
        $this->assertStringContainsString('| Second Title</title>', $second);
        $this->assertStringNotContainsString('First Title', $second);
    }

    public function test_default_seo_description_setting_is_the_fallback(): void
    {
        $this->seedProfile();
        Setting::updateOrCreate(['key' => 'default_seo_description'], ['value' => 'My own default description.']);

        $this->assertSame('My own default description.', SiteSettings::defaultSeoDescription());

        // The 404 view supplies no description, so the setting is used.
        $content = $this->get('/no-such-route-at-all')->content();
        $this->assertStringContainsString('My own default description.', $content);
    }

    public function test_blank_setting_falls_back_to_the_config_default(): void
    {
        $this->seedProfile();
        Setting::updateOrCreate(['key' => 'site_name'], ['value' => '']);

        $this->assertSame(config('app.name'), SiteSettings::siteName());
    }

    /**
     * With no `default_og_image_media_id` set, the journalist's portrait is
     * used as the social card.
     *
     * This matters more here than on a typical site: every one of the 210
     * pieces is a link-out with no body image of its own, so with no default
     * configured there was nothing at all for them to fall back to and every
     * share of his work rendered a blank preview. On a single-author site the
     * portrait is the natural card, and deriving it means a fresh install has
     * working previews with nothing to configure.
     */
    public function test_the_portrait_is_used_as_the_default_og_image(): void
    {
        $profile = $this->seedProfile();

        $media = Media::create([
            'type' => 'image',
            'file_path' => 'journalist/profile/portrait.png',
            'disk' => 'public',
            'original_filename' => 'portrait.png',
            'alt_text' => 'Portrait of the journalist',
            'uploaded_by' => $profile->user_id,
        ]);

        $profile->forceFill(['photo_media_id' => $media->id])->save();

        foreach (['/', '/work', '/archive'] as $uri) {
            $content = $this->get($uri)->assertOk()->content();

            $this->assertStringContainsString('og:image', $content, "no og:image on {$uri}");
            $this->assertStringContainsString($media->url, $content, "og:image not the portrait on {$uri}");
        }
    }

    /** An explicitly configured card must still win over the portrait. */
    public function test_a_configured_og_image_overrides_the_portrait(): void
    {
        $profile = $this->seedProfile();

        $portrait = Media::create([
            'type' => 'image',
            'file_path' => 'journalist/profile/portrait.png',
            'disk' => 'public',
            'original_filename' => 'portrait.png',
            'alt_text' => 'Portrait',
            'uploaded_by' => $profile->user_id,
        ]);
        $profile->forceFill(['photo_media_id' => $portrait->id])->save();

        $card = Media::create([
            'type' => 'image',
            'file_path' => 'media/brand-card.png',
            'disk' => 'public',
            'original_filename' => 'brand-card.png',
            'alt_text' => 'Brand card',
            'uploaded_by' => $profile->user_id,
        ]);

        Setting::updateOrCreate(['key' => 'default_og_image_media_id'], ['value' => $card->id]);

        $content = $this->get('/')->assertOk()->content();

        // Assert against the og:image tag specifically: the portrait still
        // appears on the homepage as the hero <img>, which is correct and
        // unrelated to the social card.
        preg_match('~<meta property="og:image" content="([^"]+)"~', $content, $og);

        $this->assertNotEmpty($og, 'no og:image tag emitted');

        // Media URLs are root-relative; the social card is absolutised at emit
        // time because crawlers resolve og:image against nothing.
        $this->assertSame(url($card->url), $og[1]);
    }

    /** With neither configured, no og:image tag is emitted rather than an empty one. */
    public function test_no_og_image_tag_when_there_is_no_portrait_and_no_default(): void
    {
        $this->seedProfile();

        $this->get('/')->assertOk()->assertDontSee('og:image', false);
    }

    public function test_default_og_image_is_emitted_when_set(): void
    {
        $this->seedProfile();

        $media = Media::create([
            'type' => 'image',
            'file_path' => 'media/default-og.jpg',
            'disk' => 'public',
            'original_filename' => 'default-og.jpg',
            'alt_text' => 'Site default share image',
            'uploaded_by' => User::factory()->create(['role' => 'owner'])->id,
        ]);

        Setting::updateOrCreate(['key' => 'default_og_image_media_id'], ['value' => $media->id]);

        $content = $this->get('/')->content();

        $this->assertStringContainsString('og:image', $content);
        $this->assertStringContainsString('default-og.jpg', $content);
    }

    public function test_favicon_is_linked(): void
    {
        $this->seedProfile();

        $this->assertStringContainsString('rel="icon"', $this->get('/')->content());
    }

    /**
     * A settings change is recorded in the audit trail.
     *
     * Asserted on the subject and on the new value appearing in the payload,
     * rather than on the action being `created`. The rows are now seeded by
     * `SeoSettings`' migration, so editing one is an update — and "created" vs
     * "updated" is not the property being tested. That a change to a setting
     * is attributable is.
     */
    public function test_settings_change_is_audit_logged(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']));

        Setting::updateOrCreate(['key' => 'site_name'], ['value' => 'Audited']);

        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => Setting::class,
        ]);

        $log = ActivityLog::query()
            ->where('subject_type', Setting::class)
            ->latest('id')
            ->firstOrFail();

        $this->assertStringContainsString(
            'Audited',
            (string) json_encode($log->changes),
            'The audit entry should record the new value.'
        );
    }

    // ── CMS pages ───────────────────────────────────────────────

    public function test_cms_page_is_reachable_at_its_slug(): void
    {
        $this->seedProfile();
        Page::create([
            'slug' => 'colophon',
            'title' => 'Colophon',
            'body' => 'How this site is built.',
        ]);

        $response = $this->get('/colophon');

        $response->assertOk();
        $this->assertStringContainsString('Colophon', $response->content());
        $this->assertStringContainsString('How this site is built.', $response->content());
    }

    public function test_unknown_slug_still_404s(): void
    {
        $this->seedProfile();

        $this->get('/no-such-page')->assertNotFound();
    }

    public function test_catch_all_page_route_does_not_shadow_admin_or_health(): void
    {
        $this->seedProfile();

        // /up is the health check; it must not be swallowed by /{slug}.
        $this->get('/up')->assertOk();
    }

    public function test_about_page_renders_cms_intro_block(): void
    {
        $this->seedProfile();
        Page::create([
            'slug' => 'about',
            'title' => 'About',
            'body' => 'A standalone statement of who I am.',
        ]);

        $content = $this->get('/about')->content();

        $this->assertStringContainsString('A standalone statement of who I am.', $content);
    }

    public function test_contact_page_renders_cms_intro_block(): void
    {
        $this->seedProfile();
        Page::create([
            'slug' => 'contact',
            'title' => 'Contact',
            'body' => 'Tips are welcome, always.',
        ]);

        $content = $this->get('/contact')->content();

        $this->assertStringContainsString('Tips are welcome, always.', $content);
    }

    public function test_cms_page_body_is_escaped(): void
    {
        $this->seedProfile();
        Page::create([
            'slug' => 'escape-test',
            'title' => 'Escape',
            'body' => '<script>alert(1)</script>',
        ]);

        $content = $this->get('/escape-test')->content();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $content);
        $this->assertStringContainsString('&lt;script&gt;', $content);
    }

    public function test_cms_pages_are_in_the_sitemap_but_intro_blocks_are_not(): void
    {
        $this->seedProfile();

        Page::create(['slug' => 'colophon', 'title' => 'Colophon', 'body' => 'Body.']);
        Page::create(['slug' => 'about', 'title' => 'About', 'body' => 'Body.']);
        Page::create(['slug' => 'contact', 'title' => 'Contact', 'body' => 'Body.']);

        $xml = $this->get('/sitemap.xml')->content();

        // The standalone page is advertised.
        $this->assertStringContainsString(url('/colophon'), $xml);

        // /about and /contact come from the static list only — the intro-block
        // pages must not add a second, duplicate entry (SEO.md §3).
        $this->assertSame(1, substr_count($xml, '<loc>'.url('/about').'</loc>'));
        $this->assertSame(1, substr_count($xml, '<loc>'.url('/contact').'</loc>'));
    }

    // ── Error pages ─────────────────────────────────────────────

    public function test_500_page_renders_without_touching_the_database(): void
    {
        // The 500 view must not depend on the shared layout, the profile, or
        // settings — a database failure is the most common cause of a 500, and
        // touching it here would throw a second exception inside the handler.
        $view = file_get_contents(resource_path('views/errors/500.blade.php'));

        // Strip Blade comments so the check is about markup, not prose.
        $markup = preg_replace('/\{\{--.*?--\}\}/s', '', $view);

        foreach (['x-app-layout', 'JournalistProfile', 'SiteSettings', '@vite', 'DB::'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $markup, "500 page must not reference {$forbidden}");
        }

        // Still a real, on-brand HTML document.
        $this->assertStringContainsString('<!DOCTYPE html>', $markup);
        $this->assertStringContainsString('noindex,nofollow', $markup);
        $this->assertStringContainsString('--accent: #8c1d18', $markup);
    }

    // ── Share links ─────────────────────────────────────────────

    public function test_detail_pages_offer_share_links(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->seedProfile();

        ContentItem::create([
            'author_id' => $owner->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'Shareable Piece',
            'slug' => 'shareable-piece',
            'summary' => 'Summary.',
            'body' => '<p>Body.</p>',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $content = $this->get('/articles/shareable-piece')->content();

        $this->assertStringContainsString('data-share-copy', $content);
        $this->assertStringContainsString('x.com/intent/tweet', $content);
        $this->assertStringContainsString('facebook.com/sharer', $content);
        $this->assertStringContainsString('linkedin.com/sharing', $content);
    }

    public function test_contact_message_stores_and_confirms(): void
    {
        $this->seedProfile();

        $this->post('/contact', [
            'name' => 'Reader',
            'email' => 'reader@example.com',
            'message' => 'A tip.',
        ])->assertRedirect('/contact');

        $this->assertSame(1, Contact::count());

        $this->assertStringContainsString('id="form-success"', $this->get('/contact')->content());
    }
}
