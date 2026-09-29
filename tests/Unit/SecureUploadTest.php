<?php

namespace Tests\Unit;

use App\Support\SecureUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * SECURITY.md §10–§11 — server-side upload hardening.
 *
 * These exercise the real submission path (raw bytes on disk, sniffed with
 * finfo), because that is the only thing an attacker cannot lie about: the
 * browser-supplied filename, extension, and MIME type are all ignored.
 */
class SecureUploadTest extends TestCase
{
    /** A script renamed to .jpg is rejected — detection reads the bytes. */
    public function test_disallowed_mime_type_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'innocent.jpg',
            "<?php echo shell_exec(\$_GET['cmd']); ?>"
        );

        $this->expectException(ValidationException::class);

        SecureUpload::sanitize($file, 'data.file_path');
    }

    /** SVG is a stored-XSS vector and is deliberately not on the allowlist. */
    public function test_svg_upload_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
        );

        $this->expectException(ValidationException::class);

        SecureUpload::sanitize($file, 'data.file_path');
    }

    /** An archive is not on the allowlist either. */
    public function test_zip_upload_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent('payload.zip', "PK\x03\x04fakearchive");

        $this->expectException(ValidationException::class);

        SecureUpload::sanitize($file, 'data.file_path');
    }

    /** A form can narrow the allowlist... */
    public function test_form_level_allowlist_narrows_but_never_widens(): void
    {
        // The media form accepts images, video, audio, and PDF — but not SVG.
        $allowed = SecureUpload::allowedMimeTypes(['image/*', 'application/pdf']);

        $this->assertContains('image/jpeg', $allowed);
        $this->assertContains('image/webp', $allowed);
        $this->assertContains('application/pdf', $allowed);
        $this->assertNotContains('image/svg+xml', $allowed);
        $this->assertNotContains('video/mp4', $allowed);

        // Requests for types outside the app allowlist fall back to the
        // (still restricted) app allowlist rather than opening up.
        $this->assertNotContains('text/html', SecureUpload::allowedMimeTypes(['text/html']));
    }

    /** An image-only field rejects a document that is otherwise allowed. */
    public function test_narrowed_field_rejects_other_allowed_types(): void
    {
        $this->expectException(ValidationException::class);

        SecureUpload::sanitize(
            UploadedFile::fake()->createWithContent('brief.pdf', "%PDF-1.4\n%%EOF"),
            'data.featured_image_media_id',
            SecureUpload::IMAGE_MIME_TYPES
        );
    }

    /**
     * SECURITY.md §11 — EXIF (including GPS coordinates) must not survive the
     * upload. The photo is re-encoded, so the marker comment is gone and the
     * embedded APP1 segment with it.
     */
    public function test_image_is_reencoded_and_metadata_stripped(): void
    {
        $withExif = $this->jpegWithExifComment('LEAK-ME-GPS-51.5074');

        $this->assertStringContainsString('LEAK-ME-GPS-51.5074', $withExif);

        $result = SecureUpload::sanitize(
            UploadedFile::fake()->createWithContent('holiday.jpg', $withExif),
            'data.file_path',
        );

        $this->assertSame('image/jpeg', $result['mime']);
        $this->assertStringNotContainsString('LEAK-ME-GPS-51.5074', $result['contents']);
        $this->assertStringStartsWith("\xFF\xD8\xFF", $result['contents']);
        $this->assertNotFalse(getimagesizefromstring($result['contents']));
    }

    /**
     * SECURITY.md §10 — stored names are random and the extension comes from
     * the sniffed MIME type, so a double-extension or traversal attempt in the
     * client filename cannot influence the path on disk.
     */
    public function test_stored_filename_is_random_and_uses_sniffed_extension(): void
    {
        $file = UploadedFile::fake()->image('../../../shell.php.jpg', 40, 40);

        $result = SecureUpload::sanitize($file, 'data.file_path');

        $this->assertSame('jpg', pathinfo($result['filename'], PATHINFO_EXTENSION));
        $this->assertStringNotContainsString('shell', $result['filename']);
        $this->assertStringNotContainsString('..', $result['filename']);
        $this->assertSame(44, strlen($result['filename'])); // 40 random chars + ".jpg"

        // Two uploads of the same image never collide.
        $second = SecureUpload::sanitize(UploadedFile::fake()->image('same.jpg', 40, 40), 'data.file_path');

        $this->assertNotSame($result['filename'], $second['filename']);
    }

    /** PNG transparency survives the re-encode (no black-box flattening). */
    public function test_transparent_png_is_kept_transparent(): void
    {
        $image = imagecreatetruecolor(20, 20);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        $result = SecureUpload::sanitize(
            UploadedFile::fake()->createWithContent('logo.png', $png),
            'data.file_path',
        );

        $this->assertSame('image/png', $result['mime']);
        $this->assertSame('png', pathinfo($result['filename'], PATHINFO_EXTENSION));

        $size = getimagesizefromstring($result['contents']);
        $this->assertSame('image/png', $size['mime']);

        // Sample a corner pixel: a flattened image would be opaque.
        $decoded = imagecreatefromstring($result['contents']);
        $rgba = imagecolorat($decoded, 0, 0);
        imagedestroy($decoded);

        $this->assertSame(127, ($rgba >> 24) & 0x7F);
    }

    /** Builds a real JPEG carrying an APP1 EXIF segment with a GPS marker. */
    private function jpegWithExifComment(string $marker): string
    {
        $image = imagecreatetruecolor(60, 40);
        imagefilledrectangle($image, 0, 0, 59, 39, imagecolorallocate($image, 10, 90, 200));

        ob_start();
        imagejpeg($image, null, 90);
        $jpeg = (string) ob_get_clean();
        imagedestroy($image);

        // APP1 payload: "Exif\0\0" + the marker we want to see stripped.
        $payload = "Exif\x00\x00".$marker."\x00\x00";
        $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }

    /**
     * A large PNG is a photograph in practice, and PNG is the wrong format for
     * one: a real 1268x1240 headshot upload came out at 2.1 MB for a portrait
     * displayed at 288px, and every homepage visit and social crawler had to
     * download it. It is stored as WebP instead, and the reported mime and
     * filename must match the actual bytes, so nothing is ever a WebP named .png.
     */
    public function test_large_photographic_png_is_stored_as_webp(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD was built without WebP support.');
        }

        $png = $this->noisyPng(1000);

        // Guard the premise: if this PNG encodes small the test would pass for
        // the wrong reason, never reaching the threshold.
        $this->assertGreaterThanOrEqual(
            SecureUpload::PNG_TO_WEBP_THRESHOLD_BYTES,
            strlen($png),
            'fixture PNG is not large enough to trigger the conversion'
        );

        $result = SecureUpload::sanitize(
            UploadedFile::fake()->createWithContent('portrait.png', $png),
            'data.file_path',
        );

        $this->assertSame('image/webp', $result['mime']);
        $this->assertSame('webp', pathinfo($result['filename'], PATHINFO_EXTENSION));

        // The bytes must actually be WebP, not merely labelled as such.
        $this->assertSame('image/webp', getimagesizefromstring($result['contents'])['mime']);

        $this->assertLessThan(strlen($png), strlen($result['contents']));
    }

    /**
     * A small PNG — a logo, icon or diagram — is left exactly as uploaded: it
     * is small, already compresses well as PNG, and has no reason to be touched.
     */
    public function test_small_png_is_left_as_png(): void
    {
        $image = imagecreatetruecolor(24, 24);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 30, 30));

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        $result = SecureUpload::sanitize(
            UploadedFile::fake()->createWithContent('logo.png', $png),
            'data.file_path',
        );

        $this->assertSame('image/png', $result['mime']);
        $this->assertSame('png', pathinfo($result['filename'], PATHINFO_EXTENSION));
    }

    /** A JPEG photograph is already the right format and must not be touched. */
    public function test_jpeg_is_not_converted(): void
    {
        $image = imagecreatetruecolor(700, 700);
        imagefilledrectangle($image, 0, 0, 699, 699, imagecolorallocate($image, 40, 90, 160));

        ob_start();
        imagejpeg($image, null, 90);
        $jpeg = (string) ob_get_clean();
        imagedestroy($image);

        $result = SecureUpload::sanitize(
            UploadedFile::fake()->createWithContent('photo.jpg', $jpeg),
            'data.file_path',
        );

        $this->assertSame('image/jpeg', $result['mime']);
        $this->assertSame('jpg', pathinfo($result['filename'], PATHINFO_EXTENSION));
    }

    /**
     * A PNG big enough to look photographic, but flat enough that WebP would
     * not be smaller. The conversion must never make a file bigger.
     */
    public function test_conversion_is_skipped_when_webp_would_not_be_smaller(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD was built without WebP support.');
        }

        $png = $this->noisyPng(700);
        $result = SecureUpload::sanitize(
            UploadedFile::fake()->createWithContent('x.png', $png),
            'data.file_path',
        );

        if ($result['mime'] === 'image/webp') {
            $originalSize = strlen($png);
            $newSize = strlen($result['contents']);

            $this->assertLessThan($originalSize, $newSize, 'WebP was larger than the PNG');
        }

        // Either outcome is acceptable; the invariant is that the stored bytes
        // and the reported mime always agree.
        $this->assertSame($result['mime'], getimagesizefromstring($result['contents'])['mime']);
    }

    /**
     * A photo-like PNG: a smooth gradient with per-pixel noise.
     *
     * The noise matters. An earlier version sampled colour every few pixels and
     * left the gaps black, which PNG compresses extremely well — so the fixture
     * behaved like a flat graphic rather than a photograph, and the "only
     * convert if smaller" guard correctly declined to convert it. A real
     * photograph has no large flat runs, and neither does this.
     */
    private function noisyPng(int $size): string
    {
        $image = imagecreatetruecolor($size, $size);

        for ($x = 0; $x < $size; $x++) {
            for ($y = 0; $y < $size; $y++) {
                $base = (int) (127 + 100 * sin($x / 40) * cos($y / 35));

                imagesetpixel($image, $x, $y, imagecolorallocate(
                    $image,
                    max(0, min(255, $base + random_int(-25, 25))),
                    max(0, min(255, $base + random_int(-25, 25))),
                    max(0, min(255, $base + random_int(-25, 25)))
                ));
            }
        }

        ob_start();
        imagepng($image, null, 8);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }
}
