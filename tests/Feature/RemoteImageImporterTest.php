<?php

namespace Tests\Feature;

use App\Models\JournalistProfile;
use App\Models\Media;
use App\Models\User;
use App\Support\RemoteImageImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * End-to-end tests for importing an image from a URL into the media library.
 *
 * The security denials are covered in SafeRemoteImageTest /
 * SafeRemoteImageFetchTest. What matters here is that an imported image is
 * indistinguishable from an uploaded one: stored on our disk, re-encoded by
 * SecureUpload, and reaching Media.file_path in the same relative-path form.
 */
class RemoteImageImporterTest extends TestCase
{
    use RefreshDatabase;

    private function pngBytes(int $width = 80, int $height = 80): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 30, 30));

        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();

        imagedestroy($image);

        return $bytes;
    }

    public function test_it_stores_an_imported_image_on_our_own_disk(): void
    {
        Storage::fake('public');

        Http::fake([
            '*' => Http::response($this->pngBytes(), 200, ['Content-Type' => 'image/png']),
        ]);

        $path = (new RemoteImageImporter('journalist/profile'))->import('https://1.1.1.1/portrait.png');

        Storage::disk('public')->assertExists($path);

        // Relative path, no leading slash, matching every existing row.
        $this->assertStringStartsWith('journalist/profile/', $path);
        $this->assertStringNotContainsString('\\', $path);
        $this->assertStringNotContainsString('://', $path);
    }

    /** The stored bytes are the re-encoded output, not the raw download. */
    public function test_the_stored_file_is_sanitized_output(): void
    {
        Storage::fake('public');

        Http::fake([
            '*' => Http::response($this->pngBytes(), 200, ['Content-Type' => 'image/png']),
        ]);

        $path = (new RemoteImageImporter('journalist/profile'))->import('https://1.1.1.1/portrait.png');

        $stored = Storage::disk('public')->get($path);

        $this->assertNotSame($this->pngBytes(), $stored, 'raw download must not be stored verbatim');
        $this->assertNotEmpty($stored);

        // Whatever SecureUpload decided the type is, the extension must agree —
        // a WebP named .png is the classic mismatch.
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $expected = [
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
        ];

        $this->assertArrayHasKey($extension, $expected);
        $this->assertSame($expected[$extension], (new \finfo(FILEINFO_MIME_TYPE))->buffer($stored));
    }

    public function test_it_refuses_a_url_pointing_at_the_private_network(): void
    {
        Storage::fake('public');
        Http::fake();

        $this->expectException(ValidationException::class);

        (new RemoteImageImporter('journalist/profile'))->import('http://192.168.1.1/secret.jpg');
    }

    public function test_it_refuses_svg(): void
    {
        Storage::fake('public');

        Http::fake([
            '*' => Http::response('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 200),
        ]);

        $this->expectException(ValidationException::class);

        (new RemoteImageImporter('journalist/profile'))->import('https://1.1.1.1/vector.svg');
    }

    /** A rejected import must not leave a half-written file behind. */
    public function test_a_failed_import_stores_nothing(): void
    {
        Storage::fake('public');

        Http::fake([
            '*' => Http::response('definitely not an image', 200, ['Content-Type' => 'image/png']),
        ]);

        try {
            (new RemoteImageImporter('journalist/profile'))->import('https://1.1.1.1/liar.jpg');
            $this->fail('Expected the import to be rejected.');
        } catch (ValidationException) {
            // expected
        }

        $this->assertEmpty(Storage::disk('public')->allFiles('journalist/profile'));
    }

    /**
     * The full path a user actually takes: paste a URL, get a working image on
     * the public profile, with no reference to the origin site anywhere.
     */
    public function test_the_imported_image_becomes_a_working_profile_photo(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'owner']);

        $profile = JournalistProfile::create([
            'user_id' => $user->id,
            'name' => 'Emrul Hasan Bappi',
            'title' => 'Staff Journalist',
            'slug' => 'emrul-hasan-bappi',
        ]);

        // An IP literal, so the test does not depend on DNS. A hostname would
        // be rejected at the resolve step if this environment is offline.
        Http::fake([
            '1.1.1.1/*' => Http::response($this->pngBytes(300, 300), 200, ['Content-Type' => 'image/png']),
        ]);

        $path = (new RemoteImageImporter('journalist/profile'))->import(
            'https://1.1.1.1/portrait.png',
            'Portrait of '.$profile->name
        );

        // This is the handoff the form performs: the upload field's dehydrate
        // step turns a storage path into a Media row.
        $media = Media::create([
            'type' => 'image',
            'file_path' => $path,
            'disk' => 'public',
            'original_filename' => basename($path),
            'alt_text' => 'Portrait of '.$profile->name,
            'uploaded_by' => $user->id,
        ]);

        $profile->update(['photo_media_id' => $media->id, 'photo_alt_text' => $media->alt_text]);

        $this->assertTrue($profile->refresh()->photo->exists());
        $this->assertSame('Portrait of '.$profile->name, $profile->photo->alt_text);

        // The origin host must not be recorded anywhere on the row.
        $this->assertStringNotContainsString('1.1.1.1', $media->file_path);
        $this->assertStringNotContainsString('http', $media->file_path);
    }

    public function test_it_sends_a_descriptive_user_agent(): void
    {
        Storage::fake('public');

        Http::fake(['*' => Http::response($this->pngBytes(), 200)]);

        (new RemoteImageImporter('journalist/profile'))->import('https://1.1.1.1/portrait.png');

        Http::assertSent(fn (Request $request) => str_contains(
            implode(' ', (array) $request->header('User-Agent')),
            'image import'
        ));
    }
}
