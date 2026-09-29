<?php

namespace Tests\Feature;

use App\Models\JournalistProfile;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Media URL generation.
 *
 * This covers a bug that actually happened in the running app rather than a
 * hypothetical one: `APP_URL` was `http://localhost` while the site was being
 * browsed on `http://127.0.0.1:8000`. Because the public disk's URL was built
 * from `APP_URL`, every image on every page pointed at a *different origin*.
 * The page loaded and every image 404'd — the site looked broken with no error
 * anywhere in the Laravel log, because the request never reached the app.
 */
class MediaUrlTest extends TestCase
{
    use RefreshDatabase;

    private function seedMedia(): Media
    {
        $owner = User::factory()->create(['role' => 'owner']);

        return Media::create([
            'type' => 'image',
            'file_path' => 'journalist/profile/test.webp',
            'disk' => 'public',
            'original_filename' => 'test.webp',
            'alt_text' => 'Test portrait',
            'uploaded_by' => $owner->id,
        ]);
    }

    private function seedProfileWithPhoto(): JournalistProfile
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $profile = JournalistProfile::create([
            'user_id' => $owner->id,
            'name' => 'Test Journalist',
            'title' => 'Staff Writer',
            'short_bio' => 'Short bio.',
            'long_bio' => 'Long bio.',
        ]);

        $profile->forceFill(['photo_media_id' => $this->seedMedia()->id])->save();

        return $profile->fresh();
    }

    /**
     * Media URLs must be root-relative. A root-relative URL resolves against
     * whichever origin served the page, so a mismatch in host, port or vhost
     * cannot break it.
     */
    public function test_media_urls_are_root_relative(): void
    {
        $url = $this->seedMedia()->url;

        $this->assertStringStartsWith('/storage/', $url, "media URL is not root-relative: {$url}");
        $this->assertStringNotContainsString('://', $url);
    }

    /** The src actually rendered into the page must be root-relative. */
    public function test_rendered_image_src_is_root_relative(): void
    {
        $this->seedProfileWithPhoto();

        $html = $this->get('/')->assertOk()->getContent();

        // Grab the whole tag then pull src out of it: attribute order is not
        // something to assert against, and matching alt-then-src broke when the
        // renderer emitted src first.
        $this->assertSame(
            1,
            preg_match('~<img[^>]*alt="Portrait of[^"]*"[^>]*>~', $html, $tag),
            'portrait img not found on the homepage'
        );

        $this->assertSame(1, preg_match('~src="([^"]+)"~', $tag[0], $m), 'portrait img has no src');

        $this->assertStringStartsWith('/storage/', $m[1], "rendered src is not root-relative: {$m[1]}");
    }

    /**
     * og:image is the exception: crawlers resolve it against nothing, so it has
     * to be absolute. The tag is emitted absolute even though the page's own src
     * is relative.
     */
    public function test_og_image_is_emitted_as_an_absolute_url(): void
    {
        $this->seedProfileWithPhoto();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(
            1,
            preg_match('~<meta property="og:image" content="([^"]+)"~', $html, $m),
            'no og:image emitted'
        );

        $this->assertStringStartsWith('http', $m[1], "og:image must be absolute: {$m[1]}");
        $this->assertStringContainsString('/storage/', $m[1]);
    }

    /**
     * The public disk must be marked serveable so Laravel registers a
     * `/storage/{path}` fallback.
     *
     * Without it the only way to serve public files is the web server reading
     * `public/storage` — which on Windows is a directory *junction* that Apache
     * does not traverse, so images 404 even though the app is fine. With the
     * route registered, a server that cannot follow the link still gets the
     * file, and one that can answers first and never reaches the route.
     */
    public function test_the_public_disk_is_marked_serveable(): void
    {
        $this->assertTrue(
            config('filesystems.disks.public.serve'),
            'public disk must have serve => true so Laravel can serve files directly'
        );
    }
}
