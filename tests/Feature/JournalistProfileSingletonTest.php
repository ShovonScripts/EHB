<?php

namespace Tests\Feature;

use App\Filament\Resources\JournalistProfiles\JournalistProfileResource;
use App\Filament\Resources\JournalistProfiles\Pages\EditJournalistProfile;
use App\Models\JournalistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The journalist profile is a singleton, not a collection.
 *
 * One author, one profile. The admin resource is edit-only so a second one
 * cannot be created, and the public site resolves the profile through
 * `current()` so its reads are deterministic. The bare `first()` this replaced
 * had no `ORDER BY`, so a second row would have made the homepage, the footer
 * and the 404 page each independently pick a different profile.
 */
class JournalistProfileSingletonTest extends TestCase
{
    use RefreshDatabase;

    private function seedProfile(string $name = 'Emrul Hasan Bappi'): JournalistProfile
    {
        $owner = User::factory()->create(['role' => 'owner']);

        return JournalistProfile::create([
            'user_id' => $owner->id,
            'name' => $name,
            'title' => 'Staff Journalist, The Daily Star',
            'short_bio' => 'Short bio.',
            'long_bio' => 'Long bio.',
        ]);
    }

    /** The resource must expose no create route. */
    public function test_the_resource_has_no_create_or_index_page(): void
    {
        $pages = array_keys(JournalistProfileResource::getPages());

        $this->assertSame(['edit'], $pages, 'the profile resource should be edit-only');
        $this->assertNotContains('create', $pages);
        $this->assertNotContains('index', $pages);
    }

    /** There is no route on which a second profile could be created. */
    public function test_there_is_no_create_route_in_the_panel(): void
    {
        $this->seedProfile();

        $owner = User::where('role', 'owner')->firstOrFail();
        $this->actingAs($owner);

        $this->get('/admin/journalist-profile/create')->assertNotFound();
        $this->get('/admin/journalist-profile')->assertNotFound();

        $this->assertSame(1, JournalistProfile::count());
    }

    /** The nav item lands on the editable profile. */
    public function test_navigation_points_at_the_profile_form(): void
    {
        $profile = $this->seedProfile();

        $owner = User::where('role', 'owner')->firstOrFail();
        $this->actingAs($owner);

        $url = JournalistProfileResource::getNavigationUrl();

        $this->assertStringContainsString($profile->getKey(), $url);
        $this->get($url)->assertOk()->assertSee('Staff Journalist, The Daily Star');
    }

    /** A singleton should not advertise a count of one in the nav. */
    public function test_navigation_badge_is_suppressed(): void
    {
        $this->assertNull(JournalistProfileResource::getNavigationBadge());
    }

    /**
     * Filament v5 refuses to build a nav item for a resource with no index page
     * (Resources\Resource\Concerns\HasNavigation::getNavigationItems returns []
     * when `! static::hasPage('index')`). The nav entry is therefore registered
     * explicitly on the panel. This asserts it survives — without it the
     * profile is only reachable by typing a URL.
     */
    public function test_the_profile_is_reachable_from_the_admin_nav(): void
    {
        $profile = $this->seedProfile();

        $owner = User::where('role', 'owner')->firstOrFail();
        $this->actingAs($owner);

        $html = $this->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('Author profile', $html);
        $this->assertStringContainsString(
            JournalistProfileResource::getUrl('edit', ['record' => $profile]),
            $html
        );
    }

    /**
     * The profile must not be deletable.
     *
     * This is the most dangerous thing that was exposed on the page: one click
     * on a live delete button would leave a site whose own name, header, footer,
     * /about, /contact and 404 page had all lost their source — with no obvious
     * way back, since the admin panel reads the site name from the profile too.
     * The rule is in the policy, so it holds for any path, not just the button.
     */
    public function test_the_profile_cannot_be_deleted(): void
    {
        $profile = $this->seedProfile();

        $owner = User::where('role', 'owner')->firstOrFail();
        $this->actingAs($owner);

        $this->assertFalse(Gate::allows('delete', $profile), 'delete must be denied');
        $this->assertFalse(Gate::allows('forceDelete', $profile), 'forceDelete must be denied');
        $this->assertFalse(Gate::allows('restore', $profile), 'restore must be denied');

        // And the page must not offer a delete action. Asserted on the action
        // itself rather than by searching the HTML for the word "Delete",
        // which appears all over Filament's own JS bundle (deleteUploadedFile,
        // deleteTag, …) and would pass or fail for meaningless reasons.
        Livewire::test(EditJournalistProfile::class, ['record' => $profile->getRouteKey()])
            ->assertSuccessful()
            ->assertActionDoesNotExist('delete');
    }

    /** Even bypassing the UI, the model must not be deletable through the panel's gate. */
    public function test_the_profile_survives_a_delete_attempt(): void
    {
        $profile = $this->seedProfile();

        $owner = User::where('role', 'owner')->firstOrFail();
        $this->actingAs($owner);

        // Authorised deletion would succeed; it must not be authorised.
        $response = Gate::inspect('delete', [$profile]);

        $this->assertFalse($response->allowed());
        $this->assertSame(1, JournalistProfile::count());
    }

    /**
     * The form must not offer a "which user owns this profile" dropdown.
     *
     * There is one admin and one profile, so the field has nothing to
     * distinguish, and picking the wrong row would detach the profile from the
     * user its bylines are read through (`contentItems()` is a HasManyThrough
     * on user_id).
     */
    public function test_the_form_does_not_offer_a_user_selector(): void
    {
        $profile = $this->seedProfile();

        $owner = User::where('role', 'owner')->firstOrFail();
        $this->actingAs($owner);

        $component = Livewire::test(EditJournalistProfile::class, ['record' => $profile->getRouteKey()])
            ->assertSuccessful();

        $this->assertFalse(
            $component->instance()->form->getComponent('user_id') !== null,
            'user_id should not be on the profile form'
        );
    }

    /** The columns that must stay on the form. */
    public function test_the_form_keeps_the_fields_that_matter(): void
    {
        $profile = $this->seedProfile();

        $owner = User::where('role', 'owner')->firstOrFail();
        $this->actingAs($owner);

        $form = Livewire::test(EditJournalistProfile::class, ['record' => $profile->getRouteKey()])
            ->assertSuccessful()
            ->instance()->form;

        foreach ([
            'name',
            'title',
            'photo_media_id',
            'photo_alt_text',
            'short_bio',
            'long_bio',
            'social_links.email',
            'social_links.twitter',
            'social_links.linkedin',
            'social_links.facebook',
            'skills',
        ] as $field) {
            $this->assertNotNull(
                $form->getComponent($field),
                "expected {$field} to still be on the profile form"
            );
        }
    }

    /** With no breadcrumb parent, the page must not show a misleading trail. */
    public function test_there_is_no_misleading_breadcrumb(): void
    {
        $profile = $this->seedProfile();

        $owner = User::where('role', 'owner')->firstOrFail();
        $this->actingAs($owner);

        $this->assertSame(
            [],
            (new EditJournalistProfile)->getBreadcrumbs(),
            'a leaf page with no index parent should have no breadcrumb'
        );
    }

    /**
     * The URL field must never be written to the database.
     *
     * `photo_image_url` is not a column on JournalistProfile. If it were not
     * marked dehydrated(false), saving the form would attempt to write an
     * unknown column and the profile would become unsaveable. This test saves
     * the form for real rather than only inspecting the schema, so a regression
     * surfaces as a failure here instead of a surprise in the admin.
     */
    public function test_the_url_field_is_never_saved_to_the_database(): void
    {
        $profile = $this->seedProfile();

        $owner = User::where('role', 'owner')->firstOrFail();
        $this->actingAs($owner);

        $component = Livewire::test(EditJournalistProfile::class, ['record' => $profile->getRouteKey()])
            ->assertSuccessful();

        // The field exists, and the save still succeeds with a value in it.
        $urlField = $component->instance()->form->getComponent('photo_image_url');

        $this->assertNotNull(
            $urlField,
            'expected the import-from-URL field on the profile form'
        );

        // The copy the user actually reads. Asserted on the schema because
        // Filament hydrates this form over Livewire, so the field is not in the
        // initial server HTML and cannot be checked by scraping the response.
        // (Filament exposes no getter for helper text — only a setter — so the
        // helper copy is not asserted here.)
        $this->assertStringContainsString('URL', (string) $urlField->getLabel());

        $component
            ->fillForm([
                'name' => 'Emrul Hasan Bappi',
                'title' => 'Staff Journalist, The Daily Star',
                'photo_image_url' => 'https://1.1.1.1/portrait.jpg',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseMissing('journalist_profiles', ['photo_image_url' => 'https://1.1.1.1/portrait.jpg']);
    }

    /**
     * The profile is still fully editable.
     */
    public function test_the_profile_can_still_be_edited(): void
    {
        $profile = $this->seedProfile();

        $owner = User::where('role', 'owner')->firstOrFail();
        $this->actingAs($owner);

        $this->get(JournalistProfileResource::getUrl('edit', ['record' => $profile]))
            ->assertOk()
            ->assertSee('Emrul Hasan Bappi');
    }

    /**
     * `current()` is the accessor the public site uses, so it must be stable
     * even in the state this design is protecting against — a stray second row
     * added directly in the database. Every page should then show the same
     * (original) profile rather than each picking a different one.
     */
    public function test_current_is_deterministic_even_with_a_stray_second_row(): void
    {
        $first = $this->seedProfile('The Real Profile');

        $owner = $first->user;
        JournalistProfile::create([
            'user_id' => $owner->id,
            'name' => 'A Stray Duplicate',
            'title' => 'Wrong',
            'short_bio' => 'Wrong.',
            'long_bio' => 'Wrong.',
        ]);

        $this->assertSame(2, JournalistProfile::count());

        foreach (range(1, 5) as $ignored) {
            $this->assertSame($first->id, JournalistProfile::current()?->id);
        }

        $this->assertSame('The Real Profile', JournalistProfile::current()?->name);
    }

    /** The public pages all agree on which profile they are showing. */
    public function test_public_pages_agree_on_the_profile(): void
    {
        $this->seedProfile('Consistent Name');

        // The HTML pages render the profile name; the feed carries the profile's
        // short bio as its channel description instead.
        foreach (['/', '/about', '/contact'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('Consistent Name', false);
        }

        $this->get('/feed')
            ->assertOk()
            ->assertSee('Short bio.', false);
    }
}
