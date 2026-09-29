<?php

namespace Tests\Feature;

use App\Filament\Resources\ContentItems\Pages\EditContentItem;
use App\Models\ContentItem;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression tests for two defects found on the content-item edit form.
 */
class ContentItemFormRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    private function item(array $overrides = []): ContentItem
    {
        // External content requires a publication, so every fixture needs one.
        $publication = Publication::firstOrCreate(
            ['slug' => 'the-daily-star'],
            ['name' => 'The Daily Star']
        );

        return ContentItem::create(array_merge([
            'title' => 'Justice in shifts',
            'slug' => 'justice-in-shifts',
            'status' => 'published',
            'content_type' => 'news',
            'source_type' => 'external',
            'author_id' => $this->owner()->id,
            'publication_id' => $publication->id,
            'summary' => 'A summary.',
            'external_url' => 'https://www.thedailystar.net/news/justice-shifts-42773',
        ], $overrides));
    }

    private function login(): void
    {
        $this->actingAs(User::where('role', 'owner')->firstOrFail());
    }

    /**
     * Editing the content type must not change the URL of a live article.
     *
     * The field carried ->afterStateUpdated(fn ($state, $set) => $set('slug', null))
     * with no create/edit guard, so correcting a mis-tagged published article
     * wiped its slug. Slug is required, so the save then failed until a slug was
     * retyped — and whatever was retyped silently republished the piece at a new
     * address. That is inbound links and search rankings lost to a metadata fix.
     */
    public function test_changing_content_type_does_not_wipe_the_slug(): void
    {
        $item = $this->item();
        $this->login();

        Livewire::test(EditContentItem::class, ['record' => $item->getRouteKey()])
            ->assertSuccessful()
            ->fillForm(['content_type' => 'opinion'])
            ->assertFormSet(['slug' => 'justice-in-shifts']);
    }

    /** And the article must still save at its original URL afterwards. */
    public function test_the_article_keeps_its_url_after_a_type_change(): void
    {
        $item = $this->item();
        $this->login();

        Livewire::test(EditContentItem::class, ['record' => $item->getRouteKey()])
            ->assertSuccessful()
            ->fillForm(['content_type' => 'opinion'])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $item->fresh();

        $this->assertSame('justice-in-shifts', $fresh->slug);
        $this->assertSame('opinion', $fresh->content_type);
        $this->assertSame('Justice in shifts', $fresh->title);
    }

    /**
     * Related Content must not render one checkbox per article.
     *
     * It was a CheckboxList, so the option count equalled the library size —
     * 209 rows in a single column on a 210-article site, fetched and rendered on
     * every edit page load. A searchable multi-select keeps the control usable
     * as the library grows.
     */
    public function test_related_content_does_not_render_one_checkbox_per_article(): void
    {
        $item = $this->item();
        $this->login();

        // A library large enough for the difference to be obvious.
        $author = User::where('role', 'owner')->firstOrFail();

        foreach (range(1, 40) as $n) {
            $this->item(['title' => "Other piece {$n}", 'slug' => "other-piece-{$n}", 'author_id' => $author->id]);
        }

        $html = Livewire::test(EditContentItem::class, ['record' => $item->getRouteKey()])
            ->assertSuccessful()
            ->html();

        $checkboxes = substr_count($html, 'type="checkbox"');

        // Previously this equalled the library size (41 here, 210 in production).
        $this->assertLessThan(
            10,
            $checkboxes,
            "Related Content rendered {$checkboxes} checkboxes; it must not scale with the library"
        );
    }

    /** The searchable control must still store selections. */
    public function test_related_content_can_still_be_linked(): void
    {
        $item = $this->item();
        $other = $this->item(['title' => 'A related piece', 'slug' => 'a-related-piece']);
        $this->login();

        Livewire::test(EditContentItem::class, ['record' => $item->getRouteKey()])
            ->assertSuccessful()
            ->fillForm(['relatedContent' => [$other->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('related_content', [
            'content_item_id' => $item->id,
            'related_content_item_id' => $other->id,
        ]);
    }
}
