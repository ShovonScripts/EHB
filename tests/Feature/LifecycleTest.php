<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cross-cutting lifecycle coverage (Phase 9).
 *
 * - Full publish lifecycle through the admin UI.
 * - Contact form full path.
 * - Cross-type search.
 */
class LifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    private function seedCrossTypeContent(): void
    {
        $author = $this->owner();
        $category = Category::create(['name' => 'Test', 'slug' => 'test']);

        $types = ['news', 'investigation', 'interview', 'opinion', 'video', 'photo_story', 'other'];

        foreach ($types as $type) {
            ContentItem::create([
                'author_id' => $author->id,
                'content_type' => $type,
                'source_type' => 'internal',
                'title' => ucfirst(str_replace('_', ' ', $type)).' Item',
                'slug' => $type.'-item',
                'summary' => 'Summary for '.$type,
                'body' => '<p>Body for '.$type.'.</p>',
                'status' => 'published',
                'published_at' => now(),
                'category_id' => $category->id,
            ]);

            ContentItem::create([
                'author_id' => $author->id,
                'content_type' => $type,
                'source_type' => 'external',
                'title' => 'External '.ucfirst(str_replace('_', ' ', $type)),
                'slug' => 'external-'.$type.'-item',
                'summary' => 'External summary for '.$type,
                'external_url' => 'https://example.com/'.$type,
                'status' => 'published',
                'published_at' => now(),
                'category_id' => $category->id,
            ]);
        }
    }

    // ── Publish lifecycle ───────────────────────────────────────

    /** Draft content is not publicly visible. */
    public function test_draft_is_not_publicly_visible(): void
    {
        $item = ContentItem::create([
            'author_id' => $this->owner()->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'Draft Article',
            'slug' => 'draft-article',
            'summary' => 'Summary.',
            'body' => '<p>Body.</p>',
            'status' => 'draft',
        ]);

        $this->assertNull(ContentItem::published()->where('slug', 'draft-article')->first());

        $this->get('/articles/draft-article')
            ->assertNotFound();
    }

    /** Scheduled content with future date is not publicly visible. */
    public function test_future_scheduled_is_not_publicly_visible(): void
    {
        $item = ContentItem::create([
            'author_id' => $this->owner()->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'Future Scheduled',
            'slug' => 'future-scheduled',
            'summary' => 'Summary.',
            'body' => '<p>Body.</p>',
            'status' => 'scheduled',
            'published_at' => now()->addDay(),
        ]);

        $this->assertNull(ContentItem::published()->where('slug', 'future-scheduled')->first());

        $this->get('/articles/future-scheduled')
            ->assertNotFound();
    }

    /** Published content is publicly visible. */
    public function test_published_is_publicly_visible(): void
    {
        $item = ContentItem::create([
            'author_id' => $this->owner()->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'Published Article',
            'slug' => 'published-article',
            'summary' => 'Summary.',
            'body' => '<p>Body.</p>',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->get('/articles/published-article')
            ->assertOk()
            ->assertSee('Published Article', false);
    }

    /** Scheduled item with past published_at becomes visible without status change. */
    public function test_past_scheduled_becomes_visible_without_status_change(): void
    {
        $item = ContentItem::create([
            'author_id' => $this->owner()->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'Past Scheduled',
            'slug' => 'past-scheduled',
            'summary' => 'Summary.',
            'body' => '<p>Body.</p>',
            'status' => 'scheduled',
            'published_at' => now()->subDay(),
        ]);

        $this->get('/articles/past-scheduled')
            ->assertOk()
            ->assertSee('Past Scheduled', false);
    }

    /** Admin can view the content item list. */
    public function test_admin_sees_content_items_list(): void
    {
        $owner = $this->owner();
        $item = ContentItem::create([
            'author_id' => $owner->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'Admin List Test',
            'slug' => 'admin-list-test',
            'summary' => 'Summary.',
            'body' => '<p>Body.</p>',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($owner);

        $this->get('/admin/content-items')
            ->assertOk()
            ->assertSee('Admin List Test', false);
    }

    // ── Contact form full path ──────────────────────────────────

    /** Valid contact submission creates a row and is visible in admin. */
    public function test_contact_form_full_path(): void
    {
        $this->post('/contact', [
            'name' => 'Reader One',
            'email' => 'reader@example.com',
            'subject' => 'Hello',
            'message' => 'This is a test message.',
        ])
            ->assertRedirect(route('contact.create'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('contacts', [
            'email' => 'reader@example.com',
            'subject' => 'Hello',
            'is_read' => false,
        ]);

        $owner = $this->owner();
        $this->actingAs($owner);

        $this->get('/admin/contacts')
            ->assertOk()
            ->assertSee('Reader One', false)
            ->assertSee('Hello', false);

        $this->get('/admin')
            ->assertOk()
            ->assertSee('1', false);
    }

    /** Honeypot silently drops the submission. */
    public function test_contact_honeypot_still_works(): void
    {
        $this->post('/contact', [
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'subject' => 'Spam',
            'message' => 'spamspamspam',
            'website' => 'http://spam.example',
        ])
            ->assertRedirect(route('contact.create'));

        $this->assertDatabaseMissing('contacts', [
            'email' => 'bot@example.com',
        ]);
    }

    /** Rate limit still rejects excess submissions. */
    public function test_contact_rate_limit_still_works(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->post('/contact', [
                'name' => 'User '.$i,
                'email' => 'user'.$i.'@example.com',
                'subject' => 'Test',
                'message' => 'Message '.$i,
            ]);
        }

        $this->post('/contact', [
            'name' => 'Rate Limited',
            'email' => 'ratelimited@example.com',
            'subject' => 'Test',
            'message' => 'Should be rate limited',
        ])->assertStatus(429);
    }

    // ── Cross-type search ───────────────────────────────────────

    /** /search surfaces matches across all content types. */
    public function test_cross_type_search_surfaces_matches(): void
    {
        $this->seedCrossTypeContent();

        $response = $this->get('/search?q=External');

        $response->assertOk();

        $content = $response->content();

        $types = ['news', 'investigation', 'interview', 'opinion', 'video', 'photo_story', 'other'];

        foreach ($types as $type) {
            $label = ucfirst(str_replace('_', ' ', $type));
            $this->assertStringContainsString('External '.$label, $content, "Search should surface external {$type} items");
        }
    }

    /** Draft and scheduled items never appear in search results. */
    public function test_search_excludes_draft_and_scheduled(): void
    {
        $author = $this->owner();

        ContentItem::create([
            'author_id' => $author->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'Draft Search Item',
            'slug' => 'draft-search-item',
            'summary' => 'Summary.',
            'body' => '<p>Body.</p>',
            'status' => 'draft',
        ]);

        ContentItem::create([
            'author_id' => $author->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'Scheduled Search Item',
            'slug' => 'scheduled-search-item',
            'summary' => 'Summary.',
            'body' => '<p>Body.</p>',
            'status' => 'scheduled',
            'published_at' => now()->addDay(),
        ]);

        $response = $this->get('/search?q=search');

        $response->assertOk();
        $response->assertDontSee('Draft Search Item', false);
        $response->assertDontSee('Scheduled Search Item', false);
    }
}
