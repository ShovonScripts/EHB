<?php

namespace Tests\Feature;

use App\Filament\Resources\JournalistProfiles\Pages\EditJournalistProfile;
use App\Models\JournalistProfile;
use App\Models\Media;
use App\Models\User;
use App\Support\PageCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Replacing the profile photo must reach the database.
 *
 * The live symptom this guards: an admin changes the portrait in
 * /admin/journalist-profile/1/edit, sees the file appear in
 * storage/app/public/journalist/profile/, and yet the public homepage keeps
 * showing the old photo (or a broken image). Two failure modes produce that:
 *
 * 1. The save never completes — the import-by-URL field writes its file to the
 *    final directory *immediately* (RemoteImageImporter), before any Save, so
 *    an admin who skips Save walks away with an orphaned file and an untouched
 *    `photo_media_id`. Nothing is logged, because nothing failed.
 * 2. The save runs but never reaches the record — EditRecord wraps the whole
 *    save in a database transaction, so a mid-save failure rolls back the
 *    Media row and the FK while the file written to disk stays put.
 *
 * The tests therefore drive the form the way Livewire delivers it from the
 * browser: file state arrives as an array inside the component snapshot (the
 * FileUploadStateCast keeps single files as uuid-keyed maps, and wire:model
 * updates carry arrays) — not as a bare string, a shape only fillForm()
 * produces. The chain asserted end to end: form state -> Media row -> updated
 * FK -> bumped PageCache version, which is what makes the change visible on
 * the very next anonymous request.
 */
class JournalistProfilePhotoSaveTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PHOTO = 'journalist/profile/portrait-replacement-test.webp';

    protected function setUp(): void
    {
        parent::setUp();

        // Fake the public disk so test image bytes never land in the real
        // storage/app/public/journalist/profile/ folder.
        Storage::fake('public');
    }

    private function seedProfile(): JournalistProfile
    {
        $owner = User::factory()->create(['role' => 'owner']);

        return JournalistProfile::create([
            'user_id' => $owner->id,
            'name' => 'Emrul Hasan Bappi',
            'title' => 'Staff Journalist, The Daily Star',
            'short_bio' => 'Short bio.',
            'long_bio' => 'Long bio.',
        ]);
    }

    public function test_saving_a_replacement_photo_persists_media_and_bumps_the_page_cache(): void
    {
        $profile = $this->seedProfile();

        $this->actingAs($profile->user);

        Storage::disk('public')->put(self::NEW_PHOTO, 'fake-image-bytes');

        // The profile starts with no photo, so any media id after the save is
        // proof that dehydrate ran — and the version bump is proof that the
        // public cache was invalidated in the same request.
        $versionBeforeSave = PageCache::contentVersion();

        Livewire::test(EditJournalistProfile::class, ['record' => $profile->getRouteKey()])
            // Array state: the shape a Livewire snapshot carries for file
            // fields — the same one the URL-import's `$set` (via
            // FileUploadStateCast) and a picker upload both serialize to.
            ->set('data.photo_media_id', [self::NEW_PHOTO])
            ->set('data.photo_alt_text', 'Portrait of Emrul Hasan Bappi')
            ->call('save')
            ->assertHasNoFormErrors();

        $profile->refresh();

        $this->assertNotNull($profile->photo_media_id, 'saving the form must write photo_media_id');
        $this->assertDatabaseHas('media', [
            'file_path' => self::NEW_PHOTO,
            'disk' => 'public',
            'alt_text' => 'Portrait of Emrul Hasan Bappi',
        ]);
        $this->assertSame(self::NEW_PHOTO, $profile->photo?->file_path);
        $this->assertGreaterThan(
            $versionBeforeSave,
            PageCache::contentVersion(),
            'saving the profile must invalidate the public page cache so the new photo is live immediately'
        );
    }

    /**
     * Opening the form on a profile that already has a photo and saving it
     * without touching the photo must not error — hydration puts the file path
     * back into the same field, so this is the most common save of all.
     */
    public function test_saving_untouched_with_an_existing_photo_succeeds(): void
    {
        $profile = $this->seedProfile();

        Storage::disk('public')->put('journalist/profile/existing-portrait.webp', 'fake-image-bytes');

        $media = Media::create([
            'type' => 'image',
            'file_path' => 'journalist/profile/existing-portrait.webp',
            'disk' => 'public',
            'original_filename' => 'existing-portrait.webp',
            'alt_text' => 'Portrait of Emrul Hasan Bappi',
            'uploaded_by' => $profile->user_id,
        ]);

        $profile->update(['photo_media_id' => $media->id]);

        $this->actingAs($profile->user);

        Livewire::test(EditJournalistProfile::class, ['record' => $profile->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($media->id, $profile->refresh()->photo_media_id);
        $this->assertSame(1, Media::query()->where('file_path', 'journalist/profile/existing-portrait.webp')->count());
    }

    /** Re-saving the same path reuses the existing Media row instead of duplicating it. */
    public function test_resaving_the_same_photo_does_not_duplicate_media_rows(): void
    {
        $profile = $this->seedProfile();

        $this->actingAs($profile->user);

        Storage::disk('public')->put(self::NEW_PHOTO, 'fake-image-bytes');

        $component = Livewire::test(EditJournalistProfile::class, ['record' => $profile->getRouteKey()]);

        foreach ([1, 2] as $ignored) {
            $component
                ->set('data.photo_media_id', [self::NEW_PHOTO])
                ->set('data.photo_alt_text', 'Portrait of Emrul Hasan Bappi')
                ->call('save')
                ->assertHasNoFormErrors();
        }

        $this->assertSame(
            1,
            Media::query()->where('file_path', self::NEW_PHOTO)->count()
        );
    }
}
