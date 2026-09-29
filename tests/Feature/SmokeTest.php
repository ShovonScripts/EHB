<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Final smoke-test pass covering all critical public paths
 * and admin endpoints after a fresh migration.
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    private function seedSmokeData(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $category = Category::create(['name' => 'News', 'slug' => 'news']);

        $types = ['news', 'investigation', 'interview', 'opinion', 'video', 'photo_story', 'other'];

        foreach ($types as $type) {
            ContentItem::create([
                'author_id' => $owner->id,
                'content_type' => $type,
                'source_type' => 'internal',
                'title' => 'Internal '.ucfirst(str_replace('_', ' ', $type)),
                'slug' => 'internal-'.$type,
                'summary' => 'Summary.',
                'body' => '<p>Body.</p>',
                'status' => 'published',
                'published_at' => now(),
                'category_id' => $category->id,
            ]);

            ContentItem::create([
                'author_id' => $owner->id,
                'content_type' => $type,
                'source_type' => 'external',
                'title' => 'External '.ucfirst(str_replace('_', ' ', $type)),
                'slug' => 'external-'.$type,
                'summary' => 'Summary.',
                'external_url' => 'https://example.com/'.$type,
                'status' => 'published',
                'published_at' => now(),
                'category_id' => $category->id,
            ]);
        }
    }

    public function test_homepage_loads(): void
    {
        $this->seedSmokeData();

        $this->get('/')
            ->assertOk()
            ->assertSee('Journalist', false);
    }

    public function test_internal_article_detail_loads(): void
    {
        $this->seedSmokeData();

        $this->get('/articles/internal-news')
            ->assertOk()
            ->assertSee('Internal News', false)
            ->assertSee('<p>Body.</p>', false);
    }

    public function test_external_work_detail_loads(): void
    {
        $this->seedSmokeData();

        $this->get('/work/external-news')
            ->assertOk()
            ->assertSee('External News', false)
            ->assertSee('https://example.com/news', false);
    }

    public function test_archive_loads_with_filters(): void
    {
        $this->seedSmokeData();

        $this->get('/archive')
            ->assertOk()
            ->assertSee('Archive', false);

        $this->get('/archive?type=news')
            ->assertOk()
            ->assertSee('Internal News', false);
    }

    public function test_search_returns_results(): void
    {
        $this->seedSmokeData();

        $this->get('/search?q=Internal')
            ->assertOk()
            ->assertSee('Internal News', false);
    }

    public function test_contact_form_submits(): void
    {
        $this->seedSmokeData();

        $this->post('/contact', [
            'name' => 'Smoke Test',
            'email' => 'smoke@example.com',
            'subject' => 'Hello',
            'message' => 'Smoke test message.',
        ])
            ->assertRedirect(route('contact.create'));

        $this->assertDatabaseHas('contacts', [
            'email' => 'smoke@example.com',
            'subject' => 'Hello',
        ]);
    }

    public function test_admin_login_page_loads(): void
    {
        $this->seedSmokeData();

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Login', false);
    }

    public function test_sitemap_loads(): void
    {
        $this->seedSmokeData();

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('<?xml', false)
            ->assertSee('internal-news', false);
    }

    public function test_robots_txt_loads(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('User-agent', false)
            ->assertSee('Disallow: /admin', false);
    }

    public function test_rss_feed_loads(): void
    {
        $this->seedSmokeData();

        $this->get('/feed')
            ->assertOk()
            ->assertSee('<?xml', false)
            ->assertSee('<rss', false)
            ->assertSee('Internal News', false);
    }
}
