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
 * The order of the fields on the content-item form.
 *
 * This is a workflow contract, not a cosmetic preference. The form is laid out
 * the way a piece is written — headline, dek, image, body — and that order was
 * not previously true of the form: headline and dek sat on one tab with the
 * byline and slug, the body and image on another, and there the body came
 * *before* the image. So the two halves of writing a story were split across
 * tabs, and the field that takes longest to fill sat above the one that gives
 * the piece its shape.
 *
 * Asserting the order here is what stops a future "just tidying" pass from
 * quietly putting it back.
 */
class ContentItemFormOrderTest extends TestCase
{
    use RefreshDatabase;

    private function item(string $sourceType = 'internal'): ContentItem
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $publication = Publication::firstOrCreate(['slug' => 'the-daily-star'], ['name' => 'The Daily Star']);

        $item = ContentItem::create([
            'title' => 'Justice in shifts',
            'slug' => 'justice-in-shifts',
            'status' => 'published',
            'content_type' => 'news',
            'source_type' => $sourceType,
            'author_id' => $owner->id,
            'publication_id' => $publication->id,
            'summary' => 'A summary.',
            'body' => '<p>The full article body.</p>',
            'external_url' => 'https://example.com/original',
        ]);

        $this->actingAs($owner);

        return $item;
    }

    /**
     * Fields in a tab, in render order, as name => label.
     *
     * Recurses into nested layout components (Grid, Section). Without that the
     * helper silently skipped everything wrapped in a Grid — which is most of
     * the Publishing tab — and a "no field was lost" test built on it would
     * have passed while proving nothing.
     *
     * @return array<string, string>
     */
    private function fieldsInTab(ContentItem $item, string $tabLabel): array
    {
        $form = Livewire::test(EditContentItem::class, ['record' => $item->getRouteKey()])
            ->assertSuccessful()
            ->instance()
            ->form;

        $tabs = $form->getComponents()[0];

        foreach ($tabs->getChildComponents() as $tab) {
            if ($tab->getLabel() === $tabLabel) {
                return $this->flatten($tab->getChildComponents());
            }
        }

        $this->fail("No tab labelled '{$tabLabel}' was found.");
    }

    /**
     * @param  array<mixed>  $components
     * @return array<string, string>
     */
    private function flatten(array $components): array
    {
        $fields = [];

        foreach ($components as $component) {
            $name = method_exists($component, 'getName') ? $component->getName() : null;

            if ($name) {
                $label = $component->getLabel();
                $fields[$name] = is_string($label) ? $label : $name;
            }

            if (method_exists($component, 'getChildComponents')) {
                // Nested fields come after the layout wrapper, which is what the
                // rendered order looks like.
                $fields += $this->flatten($component->getChildComponents());
            }
        }

        return $fields;
    }

    /**
     * The core of the request: headline, then dek, then image, then body.
     */
    public function test_the_write_tab_follows_the_writing_order(): void
    {
        $fields = $this->fieldsInTab($this->item(), 'Write');

        $order = array_keys($fields);

        $this->assertSame(
            ['title', 'summary', 'featured_image_media_id', 'body'],
            array_values(array_intersect($order, ['title', 'summary', 'featured_image_media_id', 'body'])),
            'The Write tab must read headline -> dek -> image -> body.'
        );
    }

    public function test_the_writing_fields_are_labelled_the_way_a_journalist_would_say_them(): void
    {
        $fields = $this->fieldsInTab($this->item(), 'Write');

        $this->assertSame('Headline', $fields['title']);
        $this->assertSame('Dek / Summary', $fields['summary']);
        $this->assertSame('Featured Image', $fields['featured_image_media_id']);
        $this->assertSame('Full Article Body', $fields['body']);
    }

    /**
     * The type selectors gate what appears below them, so they have to sit
     * above the writing surface. On another tab a new piece would look like it
     * had no body field at all until you went and changed a dropdown.
     */
    public function test_the_type_selectors_come_before_the_writing_fields(): void
    {
        $order = array_keys($this->fieldsInTab($this->item(), 'Write'));

        $this->assertLessThan(
            array_search('title', $order, true),
            array_search('content_type', $order, true),
            'content_type gates the fields below it.'
        );

        $this->assertLessThan(
            array_search('body', $order, true),
            array_search('source_type', $order, true),
            'source_type gates whether the body is shown at all.'
        );
    }

    /** Byline and slug are metadata, and do not belong in the writing flow. */
    public function test_metadata_fields_are_not_in_the_writing_flow(): void
    {
        $order = array_keys($this->fieldsInTab($this->item(), 'Write'));

        $this->assertNotContains('author_id', $order);
        $this->assertNotContains('slug', $order);
        $this->assertNotContains('tags', $order);
        $this->assertNotContains('seo_title', $order);
    }

    /** Those fields still exist — they were relocated, not dropped. */
    public function test_the_relocated_fields_are_still_reachable(): void
    {
        $details = array_keys($this->fieldsInTab($this->item(), 'Details'));

        $this->assertContains('slug', $details);
        $this->assertContains('author_id', $details);
    }

    /** The body must stay on the Write tab for external pieces' siblings. */
    public function test_the_body_is_offered_for_internal_content(): void
    {
        $order = array_keys($this->fieldsInTab($this->item('internal'), 'Write'));

        $this->assertContains('body', $order);
    }

    /** Every field the form has always had must survive the restructure. */
    public function test_no_field_was_lost_in_the_restructuring(): void
    {
        // One item, inspected four times — `item()` would collide on the
        // unique slug.
        $item = $this->item();

        $all = array_merge(
            array_keys($this->fieldsInTab($item, 'Write')),
            array_keys($this->fieldsInTab($item, 'Details')),
            array_keys($this->fieldsInTab($item, 'Publishing & SEO')),
            array_keys($this->fieldsInTab($item, 'Advanced')),
        );

        foreach ([
            'title', 'summary', 'body', 'slug', 'content_type', 'source_type',
            'author_id', 'tags', 'topics', 'relatedContent', 'seo_title',
            'seo_description', 'canonical_url_override', 'meta',
        ] as $field) {
            $this->assertContains($field, $all, "The '{$field}' field went missing.");
        }
    }
}
