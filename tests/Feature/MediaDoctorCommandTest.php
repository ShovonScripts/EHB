<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `php artisan media:doctor` — the diagnostic for "the image is in the admin,
 * but not on the site".
 *
 * These tests pin the report itself, not just the exit code: the whole value of
 * the command is that it tells apart the four causes that look identical in a
 * browser (missing file, missing symlink, a route that is not answering, a
 * cached page). A command that silently reports "healthy" when it cannot see
 * the file would be worse than no command at all.
 */
class MediaDoctorCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Pin the deployment shape: APP_URL and the media URL are exactly what
        // the command inspects, so a developer whose .env names a subdirectory
        // must not change what these tests report.
        config([
            'app.url' => 'http://localhost',
            'filesystems.disks.public.url' => '/storage',
        ]);
    }

    private function media(string $path, string $disk = 'public'): Media
    {
        return Media::create([
            'type' => 'image',
            'file_path' => $path,
            'disk' => $disk,
            'original_filename' => basename($path),
            'alt_text' => 'An image',
            'uploaded_by' => User::factory()->create(['role' => 'owner'])->id,
        ]);
    }

    /** Everything lines up, so the command reports healthy and exits zero. */
    public function test_a_healthy_pipeline_reports_success(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/ok.jpg', 'bytes');

        $this->media('media/ok.jpg');

        $this->artisan('media:doctor --no-http')
            ->assertSuccessful()
            ->expectsOutputToContain('1 of 1 files are present on disk');
    }

    /**
     * The deploy-shaped failure: the row survives, the bytes do not. The
     * command must name the row and exit non-zero, or the check is worthless in
     * a deploy script.
     */
    public function test_a_media_row_with_no_file_is_reported_as_a_problem(): void
    {
        Storage::fake('public');

        $this->media('media/gone.jpg');

        $this->artisan('media:doctor --no-http')
            ->assertExitCode(1)
            ->expectsOutputToContain('media/gone.jpg');
    }

    /** One path can be checked directly, which is what you do mid-debug. */
    public function test_a_single_file_can_be_checked(): void
    {
        Storage::fake('public');

        $this->artisan('media:doctor --file=journalist/profile/missing.webp --no-http')
            ->assertExitCode(1)
            ->expectsOutputToContain('is NOT on the public disk');

        Storage::disk('public')->put('journalist/profile/there.webp', 'bytes');

        $this->artisan('media:doctor --file=journalist/profile/there.webp --no-http')
            ->assertSuccessful()
            ->expectsOutputToContain('present on the public disk');
    }

    /**
     * An install under a subdirectory (`http://localhost/ehb/public`, the
     * XAMPP layout): APP_URL carries the path, the media URL does not, so every
     * image on the site resolves against the domain root and 404s. The command
     * has to name it and print the one-line fix — a missing symlink or a
     * missing file is not what is wrong here.
     */
    public function test_an_install_in_a_subdirectory_without_media_url_is_flagged(): void
    {
        config(['app.url' => 'http://localhost/ehb/public']);

        $this->artisan('media:doctor --no-http')
            ->assertExitCode(1)
            ->expectsOutputToContain('MEDIA_URL=/ehb/public/storage');

        // With the fix applied the same install is healthy.
        config(['filesystems.disks.public.url' => '/ehb/public/storage']);

        $this->artisan('media:doctor --no-http')->assertSuccessful();
    }

    /**
     * A row stored on the private disk is a different failure again: the file
     * exists, but its URL is not served — so it has to be called out rather
     * than counted as healthy.
     */
    public function test_a_row_on_a_non_public_disk_is_flagged(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        Storage::disk('local')->put('private/portrait.jpg', 'bytes');

        $this->media('private/portrait.jpg', disk: 'local');

        $this->artisan('media:doctor --no-http')
            ->assertExitCode(1)
            ->expectsOutputToContain("stored on the 'local' disk");
    }
}
