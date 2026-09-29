<?php

namespace Tests\Feature;

use App\Models\JournalistProfile;
use App\Models\Media;
use App\Models\User;
use App\Support\Urls;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Media URLs when the site is not served from the domain root.
 *
 * `http://localhost/ehb/public` is a normal way to run this project on XAMPP
 * and on shared hosting, and it is the one deployment a root-relative media URL
 * cannot describe: a browser resolves `/storage/…` against the *domain* root,
 * where this app does not live, so every image on every page 404s while the
 * admin panel — whose own assets are built from APP_URL — looks perfectly fine.
 *
 * The fix is one line of configuration (`MEDIA_URL`, see config/filesystems.php),
 * and it only works if the code that turns a media URL into a crawler-followable
 * absolute URL does *not* prepend the application base path a second time. That
 * is what App\Support\Urls exists for, and what these assertions pin.
 */
class MediaSubdirectoryUrlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The deployment this covers: served under /ehb/public, with the media
        // URL configured to match.
        config([
            'app.url' => 'http://localhost/ehb/public',
            'filesystems.disks.public.url' => '/ehb/public/storage',
        ]);
    }

    private function media(string $path = 'journalist/profile/portrait.jpg'): Media
    {
        return Media::create([
            'type' => 'image',
            'file_path' => $path,
            'disk' => 'public',
            'original_filename' => basename($path),
            'alt_text' => 'Portrait of the journalist',
            'uploaded_by' => User::factory()->create(['role' => 'owner'])->id,
        ]);
    }

    private function profileWithPhoto(Media $media): JournalistProfile
    {
        $profile = JournalistProfile::create([
            'user_id' => $media->uploaded_by,
            'name' => 'Emrul Hasan Bappi',
            'title' => 'Staff Writer',
            'short_bio' => 'Short bio.',
            'long_bio' => 'Long bio.',
        ]);

        $profile->forceFill(['photo_media_id' => $media->id])->save();

        return $profile->fresh();
    }

    /** The URL a page emits is root-relative *and* carries the subdirectory. */
    public function test_media_urls_include_the_subdirectory_prefix(): void
    {
        $media = $this->media();

        $this->assertSame(
            '/ehb/public/storage/journalist/profile/portrait.jpg',
            $media->url
        );
    }

    /**
     * `absolute_url` is the one conversion that must not go through `url()`:
     * `url()` prepends the application base path, and the relative media URL
     * already carries it, so the result points at a path nothing serves.
     */
    public function test_absolute_url_does_not_duplicate_the_base_path(): void
    {
        $media = $this->media();

        $expected = 'http://localhost/ehb/public/storage/journalist/profile/portrait.jpg';

        $this->assertSame($expected, $media->absolute_url);

        $this->assertNotSame(
            $expected,
            url($media->url),
            'url() duplicates the base path — media URLs must be absolutised with App\Support\Urls'
        );
    }

    /** The page's own src keeps the prefix; og:image is absolute and exact. */
    public function test_the_rendered_page_points_at_the_subdirectory(): void
    {
        $this->profileWithPhoto($this->media());

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(
            1,
            preg_match('~<img[^>]*alt="Portrait of[^"]*"[^>]*>~', $html, $tag),
            'portrait img not found on the homepage'
        );

        $this->assertSame(1, preg_match('~src="([^"]+)"~', $tag[0], $src), 'portrait img has no src');
        $this->assertStringStartsWith(
            '/ehb/public/storage/',
            $src[1],
            'the rendered src must include the subdirectory the app is served from'
        );

        $this->assertSame(
            1,
            preg_match('~<meta property="og:image" content="([^"]+)"~', $html, $og),
            'no og:image emitted'
        );

        $this->assertSame(
            'http://localhost/ehb/public/storage/journalist/profile/portrait.jpg',
            $og[1],
            'og:image must be absolute and carry the subdirectory exactly once'
        );
    }

    /**
     * The files themselves are reachable even without a usable `public/storage`.
     *
     * Laravel's own /storage route is registered at the disk's URL path, which
     * under a subdirectory is a path the request never matches — the request
     * arrives with the app's base path already removed. The route registered in
     * bootstrap/app.php puts the same handler where the request actually lands,
     * so an upload is served whether or not the web server can follow the
     * symlink.
     */
    public function test_the_storage_fallback_route_serves_files_under_a_prefixed_media_url(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/portrait.jpg', 'the-bytes');

        $this->get('/storage/media/portrait.jpg')
            ->assertOk()
            ->assertStreamedContent('the-bytes');

        $this->get('/storage/media/not-there.jpg')->assertNotFound();
    }

    /** The helper leaves alone anything it is not supposed to touch. */
    public function test_absolute_passes_through_absolute_and_empty_values(): void
    {
        $this->assertSame(
            'https://cdn.example.com/a.jpg',
            Urls::absolute('https://cdn.example.com/a.jpg')
        );
        $this->assertNull(Urls::absolute(null));
        $this->assertSame('', Urls::absolute(''));

        $absolute = Urls::absolute('/storage/a.jpg');

        $this->assertStringStartsWith('http://', (string) $absolute);
        $this->assertStringEndsWith('/storage/a.jpg', (string) $absolute);
        $this->assertSame(1, substr_count((string) $absolute, '/storage/a.jpg'));
    }
}
