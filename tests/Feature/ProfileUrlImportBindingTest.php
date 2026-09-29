<?php

namespace Tests\Feature;

use App\Filament\Resources\JournalistProfiles\Pages\EditJournalistProfile;
use App\Models\JournalistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The import-from-URL field's live binding.
 *
 * `photo_image_url` documents that it "fires on blur, not on save, so a bad or
 * unreachable URL is reported immediately". That claim was false: with no
 * `->live()` modifier, Livewire emits a deferred `wire:model`, so the value is
 * not sent to the server when the field loses focus and `afterStateUpdated`
 * never ran then. It ran on the next request — which, on a form with no other
 * live fields, meant Save.
 *
 * The visible symptom was not a wrong result but a slow, coupled one: the
 * download executed inside the save request, so a dead host blocked Save for up
 * to `SafeRemoteImage::TIMEOUT_SECONDS` (20s) with no sign anything was
 * pending, and the admin's unrelated edits were held hostage to a third-party
 * URL they was only trying out.
 *
 * This asserts the rendered binding rather than the schema. The schema can
 * look correct while the emitted attribute is deferred — that was exactly the
 * gap — so the only meaningful check is the markup the browser receives.
 */
class ProfileUrlImportBindingTest extends TestCase
{
    use RefreshDatabase;

    private function seedProfileAndSignIn(): JournalistProfile
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $profile = JournalistProfile::create([
            'user_id' => $owner->id,
            'name' => 'Emrul Hasan Bappi',
            'title' => 'Staff Journalist',
            'short_bio' => 'Short.',
            'long_bio' => 'Long.',
            'skills' => [],
            'social_links' => [],
        ]);

        $this->actingAs($owner);

        return $profile;
    }

    public function test_the_url_field_is_bound_live_on_blur(): void
    {
        $profile = $this->seedProfileAndSignIn();

        $html = Livewire::test(EditJournalistProfile::class, ['record' => $profile->getRouteKey()])
            ->assertSuccessful()
            ->html();

        $this->assertMatchesRegularExpression(
            '/wire:model\.live\.blur="data\.photo_image_url"/',
            $html,
            'photo_image_url must be live on blur, or the import runs during Save.'
        );
    }

    /**
     * The negative half. A deferred binding is what caused the bug, so assert
     * its absence explicitly — a future edit to `->live()` with different
     * options would otherwise pass on the positive check alone.
     */
    public function test_the_url_field_is_not_deferred(): void
    {
        $profile = $this->seedProfileAndSignIn();

        $html = Livewire::test(EditJournalistProfile::class, ['record' => $profile->getRouteKey()])
            ->assertSuccessful()
            ->html();

        $this->assertDoesNotMatchRegularExpression(
            '/wire:model="data\.photo_image_url"/',
            $html,
            'A plain wire:model is deferred and breaks the documented blur behaviour.'
        );
    }

    /**
     * Blurring the field must not write it to the database.
     *
     * `photo_image_url` is not a column, so if the live binding ever caused it
     * to be dehydrated on an update request, the profile would become
     * unsaveable with an unknown-column error.
     */
    public function test_a_blur_does_not_write_the_url_column(): void
    {
        $profile = $this->seedProfileAndSignIn();

        Livewire::test(EditJournalistProfile::class, ['record' => $profile->getRouteKey()])
            ->set('data.photo_image_url', 'https://1.1.1.1/portrait.jpg')
            ->assertSuccessful();

        $this->assertDatabaseMissing('journalist_profiles', [
            'photo_image_url' => 'https://1.1.1.1/portrait.jpg',
        ]);
    }
}
