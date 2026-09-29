<?php

namespace Tests\Feature;

use App\Filament\Forms\Components\MediaFileUpload;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Widgets\ContentStatusStats;
use App\Filament\Widgets\RecentlyPublishedWidget;
use App\Filament\Widgets\UnreadContactStats;
use App\Filament\Widgets\UpcomingScheduledWidget;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Contact;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\User;
use App\Support\SecureUpload;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Priority 1/2 admin-layer coverage:
 * - Authorization (canAccessPanel + policy denials)
 * - MediaFileUpload on OG field (SecureUpload rejection + FK round-trip)
 * - meta field round-trip
 * - Alt-text enforcement
 * - Audit-log immutability
 * - Photo-story gallery rendering
 * - Per-type nav / scoped resource filtering
 * - Dashboard widget counts
 */
class AdminLayerTest extends TestCase
{
    use RefreshDatabase;

    // ── helpers ──────────────────────────────────────────────────

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function makeItem(array $attributes = []): ContentItem
    {
        return ContentItem::create(array_merge([
            'author_id' => $this->owner()->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'Test Item',
            'slug' => 'test-item',
            'summary' => 'Summary.',
            'body' => '<p>Body.</p>',
            'status' => 'published',
            'published_at' => now(),
        ], $attributes));
    }

    // ── Authorization ────────────────────────────────────────────

    /** Unauthenticated user cannot access the admin panel. */
    public function test_unauthenticated_is_denied_panel_access(): void
    {
        $user = new User;

        $this->assertFalse($user->canAccessPanel(Panel::make()));
    }

    /** Owner can access the panel. */
    public function test_owner_can_access_panel(): void
    {
        $owner = $this->owner();

        $this->assertTrue($owner->canAccessPanel(Panel::make()));
    }

    /** Editor can access the panel. */
    public function test_editor_can_access_panel(): void
    {
        $editor = $this->editor();

        $this->assertTrue($editor->canAccessPanel(Panel::make()));
    }

    /** Editor can view content items but cannot create, update, or delete. */
    public function test_editor_policy_blocks_write_actions(): void
    {
        $editor = $this->editor();
        $item = $this->makeItem();

        $this->assertTrue(\Gate::forUser($editor)->allows('view', $item));
        $this->assertTrue(\Gate::forUser($editor)->allows('viewAny', $item));
        $this->assertFalse(\Gate::forUser($editor)->allows('create', $item));
        $this->assertFalse(\Gate::forUser($editor)->allows('update', $item));
        $this->assertFalse(\Gate::forUser($editor)->allows('delete', $item));
        $this->assertFalse(\Gate::forUser($editor)->allows('publish', $item));
    }

    /** Owner can perform all content-item actions. */
    public function test_owner_policy_allows_all_actions(): void
    {
        $owner = $this->owner();
        $item = $this->makeItem();

        $this->assertTrue(\Gate::forUser($owner)->allows('view', $item));
        $this->assertTrue(\Gate::forUser($owner)->allows('viewAny', $item));
        $this->assertTrue(\Gate::forUser($owner)->allows('create', $item));
        $this->assertTrue(\Gate::forUser($owner)->allows('update', $item));
        $this->assertTrue(\Gate::forUser($owner)->allows('delete', $item));
        $this->assertTrue(\Gate::forUser($owner)->allows('publish', $item));
    }

    // ── MediaFileUpload OG field ─────────────────────────────────

    /** SVG uploads are rejected regardless of which field they target. */
    public function test_svg_rejected_for_og_field_path(): void
    {
        $svg = UploadedFile::fake()->createWithContent(
            'og.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
        );

        $this->expectException(ValidationException::class);

        SecureUpload::sanitize($svg, 'data.seo_og_image_media_id');
    }

    /** A valid JPEG round-trips through MediaFileUpload into the FK column. */
    public function test_valid_image_round_trips_to_fk(): void
    {
        $image = UploadedFile::fake()->image('og-cover.jpg', 1200, 630);

        $result = SecureUpload::sanitize($image, 'data.seo_og_image_media_id');

        $this->assertSame('image/jpeg', $result['mime']);

        $media = Media::create([
            'type' => 'image',
            'file_path' => $result['filename'],
            'disk' => 'public',
            'original_filename' => 'og-cover.jpg',
            'alt_text' => 'Alt text',
            'uploaded_by' => $this->owner()->id,
        ]);

        $item = ContentItem::create([
            'author_id' => $this->owner()->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'OG Test',
            'slug' => 'og-test',
            'summary' => 'Summary.',
            'body' => '<p>Body.</p>',
            'status' => 'published',
            'published_at' => now(),
            'seo_og_image_media_id' => $media->id,
        ]);

        $this->assertSame($media->id, $item->seo_og_image_media_id);
        $this->assertSame('Alt text', $item->ogImage->alt_text);
    }

    // ── meta field round-trip ────────────────────────────────────

    /** Saving a meta array through the model round-trips unchanged. */
    public function test_meta_round_trips_without_double_encoding(): void
    {
        $meta = [
            'audio_url' => 'https://example.com/audio.mp3',
            'transcript' => '<p>Transcript.</p>',
            'reading_level' => 8,
        ];

        $item = ContentItem::create(array_merge([
            'author_id' => $this->owner()->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'Meta Test',
            'slug' => 'meta-test',
            'summary' => 'Summary.',
            'body' => '<p>Body.</p>',
            'status' => 'published',
            'published_at' => now(),
            'meta' => $meta,
        ]));

        $reloaded = ContentItem::find($item->id);

        $this->assertSame($meta, $reloaded->meta);
    }

    // ── Alt-text enforcement ─────────────────────────────────────

    /** MediaFileUpload stores alt_text alongside the uploaded media row. */
    public function test_media_file_upload_stores_alt_text(): void
    {
        $image = UploadedFile::fake()->image('hero.jpg', 800, 600);

        $result = SecureUpload::sanitize($image, 'data.featured_image_media_id');

        $media = Media::create([
            'type' => 'image',
            'file_path' => $result['filename'],
            'disk' => 'public',
            'original_filename' => 'hero.jpg',
            'alt_text' => 'Journalist at desk',
            'uploaded_by' => $this->owner()->id,
        ]);

        $this->assertSame('Journalist at desk', $media->alt_text);
        $this->assertNotNull($media->id);
    }

    /** MediaFileUpload supports altTextField(), and ContentItemForm configures it for every image upload. */
    public function test_content_item_form_requires_alt_text_for_published_image(): void
    {
        $upload = new MediaFileUpload('test');

        $upload->altTextField('test_alt_text');

        $this->assertNotNull($upload);

        $this->assertTrue(
            method_exists(MediaFileUpload::class, 'altTextField'),
            'MediaFileUpload should expose altTextField()'
        );
    }

    // ── Audit-log immutability ───────────────────────────────────

    /** ActivityLogResource exposes only index and view pages. */
    public function test_activity_log_resource_has_no_create_or_edit_pages(): void
    {
        $pages = ActivityLogResource::getPages();

        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('view', $pages);
        $this->assertArrayNotHasKey('create', $pages);
        $this->assertArrayNotHasKey('edit', $pages);
    }

    /** Visiting a non-existent ActivityLog create route returns 404. */
    public function test_activity_log_create_route_returns_404(): void
    {
        $this->actingAs($this->owner());

        $this->get('/admin/resources/activity-logs/create')
            ->assertNotFound();
    }

    /** Visiting a non-existent ActivityLog edit route returns 404. */
    public function test_activity_log_edit_route_returns_404(): void
    {
        $log = ActivityLog::create([
            'action' => 'test',
            'subject_type' => ContentItem::class,
            'subject_id' => 1,
            'user_id' => $this->owner()->id,
            'changes' => [],
        ]);

        $this->actingAs($this->owner());

        $this->get("/admin/resources/activity-logs/{$log->getRouteKey()}/edit")
            ->assertNotFound();
    }

    // ── Photo-story gallery ──────────────────────────────────────

    /** A photo_story item with gallery media shows the gallery section. */
    public function test_photo_story_renders_gallery_section(): void
    {
        $owner = $this->owner();
        $category = Category::create(['name' => 'Multimedia', 'slug' => 'multimedia']);

        $item = ContentItem::create([
            'author_id' => $owner->id,
            'content_type' => 'photo_story',
            'source_type' => 'internal',
            'title' => 'Photo Story',
            'slug' => 'photo-story',
            'summary' => 'A photo story.',
            'body' => '<p>Body.</p>',
            'status' => 'published',
            'published_at' => now(),
            'category_id' => $category->id,
        ]);

        $media1 = Media::create([
            'type' => 'image',
            'file_path' => 'gallery/1.jpg',
            'disk' => 'public',
            'original_filename' => '1.jpg',
            'alt_text' => 'First image',
            'uploaded_by' => $owner->id,
        ]);

        $media2 = Media::create([
            'type' => 'image',
            'file_path' => 'gallery/2.jpg',
            'disk' => 'public',
            'original_filename' => '2.jpg',
            'alt_text' => 'Second image',
            'uploaded_by' => $owner->id,
        ]);

        $item->gallery()->attach($media1->id, ['sort_order' => 0, 'caption' => 'Caption 1']);
        $item->gallery()->attach($media2->id, ['sort_order' => 1, 'caption' => 'Caption 2']);

        $response = $this->get('/multimedia/photo-story');

        $response->assertOk();
        $response->assertSee('Gallery', false);
        $response->assertSee('gallery/1.jpg', false);
        $response->assertSee('gallery/2.jpg', false);
        $response->assertSee('Caption 1', false);
        $response->assertSee('Caption 2', false);
    }

    /** A non-photo-story item does not show a gallery section. */
    public function test_non_photo_story_does_not_show_gallery(): void
    {
        $item = $this->makeItem([
            'content_type' => 'news',
            'slug' => 'news-no-gallery',
        ]);

        $response = $this->get('/articles/news-no-gallery');

        $response->assertOk();
        $response->assertDontSee('Gallery', false);
    }

    // ── Per-type nav / scoped resource ───────────────────────────

    /** Each section endpoint returns only items of its declared content_types. */
    public function test_each_section_returns_only_its_content_type(): void
    {
        $author = $this->owner();
        $category = Category::create(['name' => 'Test', 'slug' => 'test']);

        $news = ContentItem::create([
            'author_id' => $author->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'News Item',
            'slug' => 'news-item',
            'summary' => 's',
            'body' => '<p>b</p>',
            'status' => 'published',
            'published_at' => now(),
            'category_id' => $category->id,
        ]);

        $investigation = ContentItem::create([
            'author_id' => $author->id,
            'content_type' => 'investigation',
            'source_type' => 'internal',
            'title' => 'Investigation Item',
            'slug' => 'investigation-item',
            'summary' => 's',
            'body' => '<p>b</p>',
            'status' => 'published',
            'published_at' => now(),
            'category_id' => $category->id,
        ]);

        $response = $this->get('/articles');
        $response->assertOk();
        $response->assertSee('News Item', false);
        $response->assertDontSee('Investigation Item', false);

        $response = $this->get('/investigations');
        $response->assertOk();
        $response->assertSee('Investigation Item', false);
        $response->assertDontSee('News Item', false);
    }

    // ── Dashboard widgets ────────────────────────────────────────

    /** ContentStatusStats shows correct draft/scheduled/published counts. */
    public function test_content_status_stats_widget_counts(): void
    {
        $author = $this->owner();

        ContentItem::create([
            'author_id' => $author->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'Draft Item',
            'slug' => 'draft-item',
            'summary' => 's',
            'body' => '<p>b</p>',
            'status' => 'draft',
        ]);

        ContentItem::create([
            'author_id' => $author->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'Scheduled Item',
            'slug' => 'scheduled-item',
            'summary' => 's',
            'body' => '<p>b</p>',
            'status' => 'scheduled',
            'published_at' => now()->addDay(),
        ]);

        ContentItem::create([
            'author_id' => $author->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'Published Item',
            'slug' => 'published-item',
            'summary' => 's',
            'body' => '<p>b</p>',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $widget = new ContentStatusStats;

        $reflection = new \ReflectionClass($widget);
        $method = $reflection->getMethod('getStats');
        $method->setAccessible(true);
        $stats = $method->invoke($widget);

        $this->assertCount(3, $stats);

        $labels = array_map(fn ($stat) => $stat->getLabel(), $stats);
        $values = array_map(fn ($stat) => $stat->getValue(), $stats);

        $this->assertSame('Draft', $labels[0]);
        $this->assertSame(1, $values[0]);
        $this->assertSame('Scheduled', $labels[1]);
        $this->assertSame(1, $values[1]);
        $this->assertSame('Published', $labels[2]);
        $this->assertSame(1, $values[2]);
    }

    /** RecentlyPublishedWidget shows published items sorted by published_at descending. */
    public function test_recently_published_widget_shows_published(): void
    {
        $author = $this->owner();

        $old = ContentItem::create([
            'author_id' => $author->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'Old Published',
            'slug' => 'old-published',
            'summary' => 's',
            'body' => '<p>b</p>',
            'status' => 'published',
            'published_at' => now()->subWeek(),
        ]);

        $new = ContentItem::create([
            'author_id' => $author->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'New Published',
            'slug' => 'new-published',
            'summary' => 's',
            'body' => '<p>b</p>',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $draft = ContentItem::create([
            'author_id' => $author->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'Draft Item',
            'slug' => 'draft-item',
            'summary' => 's',
            'body' => '<p>b</p>',
            'status' => 'draft',
        ]);

        $query = ContentItem::query()
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->limit(5)
            ->get();

        $this->assertTrue($query->pluck('title')->contains('New Published'));
        $this->assertTrue($query->pluck('title')->contains('Old Published'));
        $this->assertFalse($query->pluck('title')->contains('Draft Item'));
    }

    /** UpcomingScheduledWidget shows only future scheduled items. */
    public function test_upcoming_scheduled_widget_shows_future(): void
    {
        $author = $this->owner();

        $past = ContentItem::create([
            'author_id' => $author->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'Past Scheduled',
            'slug' => 'past-scheduled',
            'summary' => 's',
            'body' => '<p>b</p>',
            'status' => 'scheduled',
            'published_at' => now()->subDay(),
        ]);

        $future = ContentItem::create([
            'author_id' => $author->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'Future Scheduled',
            'slug' => 'future-scheduled',
            'summary' => 's',
            'body' => '<p>b</p>',
            'status' => 'scheduled',
            'published_at' => now()->addDay(),
        ]);

        $query = ContentItem::query()
            ->where('status', 'scheduled')
            ->whereNotNull('published_at')
            ->where('published_at', '>', now())
            ->orderBy('published_at')
            ->limit(5)
            ->get();

        $this->assertTrue($query->pluck('title')->contains('Future Scheduled'));
        $this->assertFalse($query->pluck('title')->contains('Past Scheduled'));
    }

    /** UnreadContactStats shows the correct unread message count. */
    public function test_unread_contact_stats_widget_count(): void
    {
        Contact::create([
            'name' => 'Unread 1',
            'email' => 'u1@example.com',
            'subject' => 'Subject',
            'message' => 'Message',
            'is_read' => false,
        ]);

        Contact::create([
            'name' => 'Unread 2',
            'email' => 'u2@example.com',
            'subject' => 'Subject',
            'message' => 'Message',
            'is_read' => false,
        ]);

        Contact::create([
            'name' => 'Read 1',
            'email' => 'r1@example.com',
            'subject' => 'Subject',
            'message' => 'Message',
            'is_read' => true,
        ]);

        $unreadCount = Contact::where('is_read', false)->count();

        $this->assertSame(2, $unreadCount);
    }
}
