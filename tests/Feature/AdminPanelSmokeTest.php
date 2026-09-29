<?php

namespace Tests\Feature;

use App\Filament\Resources\ContentItems\Pages\EditContentItem;
use App\Filament\Widgets\ContentStatusStats;
use App\Filament\Widgets\QuickNewContentWidget;
use App\Filament\Widgets\RecentActivityWidget;
use App\Filament\Widgets\RecentlyPublishedWidget;
use App\Filament\Widgets\UnreadContactStats;
use App\Filament\Widgets\UpcomingScheduledWidget;
use App\Models\ActivityLog;
use App\Models\Award;
use App\Models\CareerHistory;
use App\Models\Category;
use App\Models\Contact;
use App\Models\ContentItem;
use App\Models\Education;
use App\Models\JournalistProfile;
use App\Models\Media;
use App\Models\Page;
use App\Models\Publication;
use App\Models\Redirect;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * End-to-end coverage of the Filament admin panel.
 *
 * Every page is requested with real rows present, so table/image rendering is
 * exercised too — this is what catches Filament v3 → v5 API drift that unit
 * tests on models cannot see (missing component classes, removed column
 * methods, array-form defaultSort(), mis-typed upload closures).
 */
class AdminPanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Media $media;

    private ContentItem $newsItem;

    private ContentItem $investigationItem;

    private JournalistProfile $profile;

    private CareerHistory $careerHistory;

    private Education $education;

    private Award $award;

    private Category $category;

    private Tag $tag;

    private Topic $topic;

    private Publication $publication;

    private Contact $contact;

    private Page $page;

    private Redirect $redirect;

    private Setting $setting;

    private ActivityLog $activityLog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => 'owner', 'name' => 'Test Owner']);

        $this->media = Media::create([
            'type' => 'image',
            'file_path' => 'admin-smoke/photo.jpg',
            'disk' => 'public',
            'original_filename' => 'photo.jpg',
            'alt_text' => 'A photo',
            'uploaded_by' => $this->owner->id,
        ]);

        $this->profile = JournalistProfile::create([
            'user_id' => $this->owner->id,
            'name' => 'Test Journalist',
            'title' => 'Investigative Reporter',
            'photo_media_id' => $this->media->id,
            'short_bio' => 'Short bio.',
        ]);

        $this->careerHistory = CareerHistory::create([
            'journalist_profile_id' => $this->profile->id,
            'role' => 'Reporter',
            'organization' => 'The Daily',
            'start_date' => now()->subYears(3)->toDateString(),
            'sort_order' => 1,
        ]);

        $this->education = Education::create([
            'journalist_profile_id' => $this->profile->id,
            'institution' => 'University',
            'program' => 'Journalism',
            'start_date' => now()->subYears(10)->toDateString(),
            'sort_order' => 1,
        ]);

        $this->award = Award::create([
            'journalist_profile_id' => $this->profile->id,
            'title' => 'Best Investigation',
            'awarding_body' => 'Press Council',
            'year' => 2024,
            'media_id' => $this->media->id,
            'sort_order' => 1,
        ]);

        $this->category = Category::create(['name' => 'Politics', 'slug' => 'politics']);
        $this->tag = Tag::create(['name' => 'Corruption', 'slug' => 'corruption']);
        $this->topic = Topic::create([
            'name' => 'Governance',
            'slug' => 'governance',
            'featured_image_media_id' => $this->media->id,
        ]);
        $this->publication = Publication::create([
            'name' => 'Example Daily',
            'slug' => 'example-daily',
            'logo_media_id' => $this->media->id,
        ]);

        $this->newsItem = ContentItem::create([
            'author_id' => $this->owner->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'News Piece',
            'slug' => 'news-piece',
            'summary' => 'Summary.',
            'body' => '<p>Body.</p>',
            'status' => 'published',
            'published_at' => now()->subHour(),
            'featured_image_media_id' => $this->media->id,
            'seo_og_image_media_id' => $this->media->id,
        ]);

        $this->investigationItem = ContentItem::create([
            'author_id' => $this->owner->id,
            'content_type' => 'investigation',
            'source_type' => 'internal',
            'title' => 'Investigation Piece',
            'slug' => 'investigation-piece',
            'summary' => 'Summary.',
            'body' => '<p>Body.</p>',
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);

        $this->contact = Contact::create([
            'name' => 'Reader',
            'email' => 'reader@example.com',
            'subject' => 'Tip',
            'message' => 'A tip.',
            'is_read' => false,
        ]);

        $this->page = Page::create(['slug' => 'about', 'title' => 'About', 'body' => '<p>About.</p>']);
        $this->redirect = Redirect::create(['from_path' => '/old', 'to_path' => '/new']);
        // 'site_name' is the real key read by App\Support\SiteSettings.
        $this->setting = Setting::updateOrCreate(['key' => 'site_name'], ['value' => 'Emrul Hasan Bappi']);

        // Every model above logs activity via RecordsActivity, and those rows
        // share the same second, so limit()-based dashboard assertions would be
        // non-deterministic. Keep a single known row.
        ActivityLog::query()->delete();

        $this->activityLog = ActivityLog::create([
            'action' => 'created',
            'subject_type' => ContentItem::class,
            'subject_id' => $this->newsItem->id,
            'user_id' => $this->owner->id,
            'changes' => [],
        ]);
    }

    // ── dashboard ────────────────────────────────────────────────

    /** The dashboard renders without the framework's stock welcome/info widgets. */
    public function test_dashboard_renders_without_the_stock_widgets(): void
    {
        $response = $this->actingAs($this->owner)->get('/admin');

        $response->assertOk();
        $response->assertDontSee('fi-account-widget');
        $response->assertDontSee('fi-filament-info-widget');
    }

    /**
     * Every dashboard widget renders its own content.
     * Widgets lazy-load in Filament v5, so they are mounted directly here.
     */
    public function test_dashboard_widgets_render_their_content(): void
    {
        Livewire::actingAs($this->owner)->test(ContentStatusStats::class)
            ->assertOk()
            ->assertSee('Draft')
            ->assertSee('Scheduled')
            ->assertSee('Published');

        Livewire::actingAs($this->owner)->test(UnreadContactStats::class)
            ->assertOk()
            ->assertSee('Unread Messages')
            ->assertSee('Total Inquiries');

        Livewire::actingAs($this->owner)->test(QuickNewContentWidget::class)
            ->assertOk()
            ->assertSee('Quick Create Content');

        Livewire::actingAs($this->owner)->test(RecentlyPublishedWidget::class)
            ->assertOk()
            ->assertSee('News Piece');

        Livewire::actingAs($this->owner)->test(UpcomingScheduledWidget::class)
            ->assertOk()
            ->assertSee('Nothing scheduled');

        Livewire::actingAs($this->owner)->test(RecentActivityWidget::class)
            ->assertOk()
            ->assertSee('Test Owner')
            ->assertSee('created');
    }

    // ── every resource page renders ──────────────────────────────

    /** @return array<string, string> */
    private static function indexPaths(): array
    {
        return [
            'content-items' => '/admin/content-items',
            'categories' => '/admin/categories',
            'tags' => '/admin/tags',
            'topics' => '/admin/topics',
            'publications' => '/admin/publications',
            'career-histories' => '/admin/career-histories',
            'education' => '/admin/education',
            'awards' => '/admin/awards',
            'media' => '/admin/media',
            'contacts' => '/admin/contacts',
            'pages' => '/admin/pages',
            'redirects' => '/admin/redirects',
            'settings' => '/admin/settings',
            'activity-logs' => '/admin/activity-logs',
            // Journalist profiles are deliberately absent: the site has one
            // author, so that resource is edit-only with no index and no
            // create route. test_the_profile_is_a_singleton_form_covers it.
        ];
    }

    /** @return array<string, string> */
    private function createPaths(): array
    {
        // Contacts deliberately have no usable create page (ContactPolicy::create
        // denies it — submissions only arrive from the public form).
        return collect(self::indexPaths())
            ->except(['contacts', 'activity-logs'])
            ->map(fn (string $path): string => $path.'/create')
            ->all();
    }

    /** @return array<string, string> */
    private function editPaths(): array
    {
        return [
            'content-items' => '/admin/content-items/'.$this->newsItem->slug.'/edit',
            'categories' => '/admin/categories/'.$this->category->id.'/edit',
            'tags' => '/admin/tags/'.$this->tag->id.'/edit',
            'topics' => '/admin/topics/'.$this->topic->id.'/edit',
            'publications' => '/admin/publications/'.$this->publication->id.'/edit',
            'journalist-profiles' => '/admin/journalist-profile/'.$this->profile->id.'/edit',
            'career-histories' => '/admin/career-histories/'.$this->careerHistory->id.'/edit',
            'education' => '/admin/education/'.$this->education->id.'/edit',
            'awards' => '/admin/awards/'.$this->award->id.'/edit',
            'media' => '/admin/media/'.$this->media->id.'/edit',
            'contacts' => '/admin/contacts/'.$this->contact->id.'/edit',
            'pages' => '/admin/pages/'.$this->page->slug.'/edit',
            'redirects' => '/admin/redirects/'.$this->redirect->id.'/edit',
            'settings' => '/admin/settings/'.$this->setting->id.'/edit',
        ];
    }

    /** Every resource index page renders with a row present. */
    public function test_every_index_page_renders(): void
    {
        $this->actingAs($this->owner);

        foreach (self::indexPaths() as $name => $path) {
            $this->assertSame(200, $this->get($path)->getStatusCode(), "Index page failed: {$name}");
        }
    }

    /** Every create page renders (these exercise the full upload/form schema). */
    public function test_every_create_page_renders(): void
    {
        $this->actingAs($this->owner);

        foreach ($this->createPaths() as $name => $path) {
            $this->assertSame(200, $this->get($path)->getStatusCode(), "Create page failed: {$name}");
        }
    }

    /** Every edit page renders with a populated record. */
    public function test_every_edit_page_renders(): void
    {
        $this->actingAs($this->owner);

        foreach ($this->editPaths() as $name => $path) {
            $this->assertSame(200, $this->get($path)->getStatusCode(), "Edit page failed: {$name}");
        }
    }

    /** The read-only activity log view page renders. */
    public function test_activity_log_view_page_renders(): void
    {
        $this->actingAs($this->owner)
            ->get('/admin/activity-logs/'.$this->activityLog->id)
            ->assertOk();
    }

    /** Contacts are read-only in the admin; the create action must stay denied. */
    public function test_contacts_create_page_is_forbidden(): void
    {
        $this->actingAs($this->owner)
            ->get('/admin/contacts/create')
            ->assertForbidden();
    }

    // ── content list behaviour ───────────────────────────────────

    /** Each content-type tab scopes the table query to its own type. */
    public function test_content_type_tabs_scope_the_table_query(): void
    {
        $this->actingAs($this->owner);

        $this->get('/admin/content-items')
            ->assertOk()
            ->assertSee('News Piece')
            ->assertSee('Investigation Piece');

        $this->get('/admin/content-items?tab=investigation')
            ->assertOk()
            ->assertSee('Investigation Piece')
            ->assertDontSee('News Piece');

        $this->get('/admin/content-items?tab=news')
            ->assertOk()
            ->assertSee('News Piece')
            ->assertDontSee('Investigation Piece');
    }

    /** A ?content_type= deep link (used by the dashboard links) selects that tab. */
    public function test_content_type_query_string_selects_the_matching_tab(): void
    {
        $this->actingAs($this->owner)
            ->get('/admin/content-items?content_type=investigation')
            ->assertOk()
            ->assertSee('Investigation Piece')
            ->assertDontSee('News Piece');
    }

    /** Quick-create links pre-select the content type, so type-aware fields appear. */
    public function test_quick_create_link_preselects_the_content_type(): void
    {
        $this->actingAs($this->owner);

        $this->get('/admin/content-items/create?content_type=interview')
            ->assertOk()
            ->assertSee('Interviewee Name');

        // Without the parameter the type-aware fields stay hidden.
        $this->get('/admin/content-items/create')
            ->assertOk()
            ->assertDontSee('Interviewee Name');
    }

    /** The list page must offer exactly one create action, not two. */
    public function test_content_list_shows_a_single_create_action(): void
    {
        $response = $this->actingAs($this->owner)->get('/admin/content-items');

        $response->assertOk();
        $this->assertSame(
            1,
            substr_count($response->getContent(), 'New content item'),
            'The content list renders more than one create button.'
        );
    }

    /**
     * The photo-story gallery must save through the pivot model, so captions and
     * drag order land on content_item_media — exactly what the public view
     * (resources/views/sections/show.blade.php) reads via $media->pivot.
     */
    public function test_photo_story_gallery_persists_pivot_caption_and_order(): void
    {
        $item = ContentItem::create([
            'author_id' => $this->owner->id,
            'content_type' => 'photo_story',
            'source_type' => 'internal',
            'title' => 'Photo Story Piece',
            'slug' => 'photo-story-piece',
            'summary' => 'Summary.',
            'body' => '<p>Body.</p>',
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);

        Livewire::actingAs($this->owner)
            ->test(EditContentItem::class, ['record' => $item->slug])
            ->fillForm([
                'galleryItems' => [
                    'gallery-1' => [
                        // FileUpload raw state is a uuid-keyed array of paths,
                        // not the media id — ids only appear after dehydration.
                        'media_id' => [
                            'f0000000-0000-4000-8000-000000000001' => $this->media->file_path,
                        ],
                        'alt_text' => 'An aerial photograph',
                        'caption' => 'A caption the public view renders',
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('content_item_media', [
            'content_item_id' => $item->id,
            'media_id' => $this->media->id,
            'caption' => 'A caption the public view renders',
            'sort_order' => 1,
        ]);
    }
}
