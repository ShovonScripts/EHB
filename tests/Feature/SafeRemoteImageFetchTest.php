<?php

namespace Tests\Feature;

use App\Support\SafeRemoteImage;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Tests for the download half of the remote image import, with HTTP faked.
 *
 * The point of interest is the redirect chain: a public URL that answers 302 to
 * a private address is the standard way SSRF filters are bypassed, and the
 * only reason this class is safe is that each hop is re-validated.
 */
class SafeRemoteImageFetchTest extends TestCase
{
    /**
     * A real, encoded image, generated rather than read from disk.
     *
     * finfo sniffs genuine encoded bytes, so a magic-number string would not do
     * — and depending on a file in public/storage would make the test depend on
     * whatever has been uploaded, which is not a stable fixture.
     */
    private function pngBytes(): string
    {
        $image = imagecreatetruecolor(64, 64);
        imagefill($image, 0, 0, imagecolorallocate($image, 40, 80, 160));

        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();

        imagedestroy($image);

        return $bytes;
    }

    public function test_it_downloads_and_sniffs_an_image(): void
    {
        Http::fake([
            '1.1.1.1/*' => Http::response($this->pngBytes(), 200, ['Content-Type' => 'image/png']),
        ]);

        $result = SafeRemoteImage::fetch('https://1.1.1.1/portrait.png');

        $this->assertNotEmpty($result['contents']);
        $this->assertSame('image/png', $result['mime']);
    }

    /**
     * The header lies about the type; the bytes decide.
     *
     * A server claiming image/jpeg while sending HTML is how an attacker gets
     * attacker-controlled markup stored as if it were an image.
     */
    public function test_it_trusts_the_bytes_not_the_content_type_header(): void
    {
        Http::fake([
            '1.1.1.1/*' => Http::response('<html><script>alert(1)</script></html>', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $this->expectException(ValidationException::class);

        SafeRemoteImage::fetch('https://1.1.1.1/evil.jpg');
    }

    public function test_it_rejects_a_non_image_body(): void
    {
        Http::fake([
            '1.1.1.1/*' => Http::response('just some text', 200, ['Content-Type' => 'text/plain']),
        ]);

        $this->expectException(ValidationException::class);

        SafeRemoteImage::fetch('https://1.1.1.1/notes.txt');
    }

    /**
     * SVG is rejected.
     *
     * SVG is a scriptable document. It is not on SecureUpload's allowlist and
     * must not become reachable through the back door of the URL importer.
     */
    public function test_it_rejects_svg(): void
    {
        Http::fake([
            '1.1.1.1/*' => Http::response(
                '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
                200,
                ['Content-Type' => 'image/svg+xml']
            ),
        ]);

        $this->expectException(ValidationException::class);

        SafeRemoteImage::fetch('https://1.1.1.1/vector.svg');
    }

    /**
     * The redirect bypass. A public URL redirects to the metadata service; the
     * second hop must be caught.
     */
    public function test_it_blocks_a_redirect_to_a_private_address(): void
    {
        Http::fake([
            // Public host, 302 to link-local — the classic SSRF pivot.
            '1.1.1.1/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
        ]);

        $this->expectException(ValidationException::class);

        SafeRemoteImage::fetch('https://1.1.1.1/redirect');
    }

    public function test_it_blocks_a_redirect_to_loopback(): void
    {
        Http::fake([
            '1.1.1.1/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1:3306/']),
        ]);

        $this->expectException(ValidationException::class);

        SafeRemoteImage::fetch('https://1.1.1.1/redirect');
    }

    /** A redirect chain that never resolves to anything fetchable must stop. */
    public function test_it_gives_up_on_a_redirect_loop(): void
    {
        Http::fake([
            '*' => Http::response('', 302, ['Location' => 'https://1.1.1.1/loop']),
        ]);

        $this->expectException(ValidationException::class);

        SafeRemoteImage::fetch('https://1.1.1.1/loop');
    }

    /** A relative Location is resolved against the current URL, then validated. */
    public function test_it_follows_a_relative_redirect(): void
    {
        Http::fakeSequence()
            ->push('', 302, ['Location' => '/images/real.png'])
            ->push($this->pngBytes(), 200, ['Content-Type' => 'image/png']);

        $result = SafeRemoteImage::fetch('https://1.1.1.1/start');

        $this->assertSame('image/png', $result['mime']);
    }

    public function test_it_rejects_a_body_over_the_size_cap(): void
    {
        Http::fake([
            '1.1.1.1/*' => Http::response('', 200, ['Content-Length' => (string) (SafeRemoteImage::MAX_BYTES + 1)]),
        ]);

        $this->expectException(ValidationException::class);

        SafeRemoteImage::fetch('https://1.1.1.1/huge.jpg');
    }

    /**
     * An understated Content-Length must not let an oversized body through.
     *
     * The header is only a claim by an untrusted server, so the cap is really
     * enforced by counting bytes as they stream. This test sends a body past
     * the cap while declaring a small length, and expects the rejection.
     */
    public function test_it_enforces_the_cap_even_when_the_header_lies(): void
    {
        // Just over the cap. A genuine 10 MB fixture in a unit test is
        // affordable, and it is the only way to prove the streaming count
        // actually fires rather than trusting the header.
        $oversized = str_repeat('A', SafeRemoteImage::MAX_BYTES + 1024);

        Http::fake([
            '1.1.1.1/*' => Http::response($oversized, 200, ['Content-Length' => '1024']),
        ]);

        $this->expectException(ValidationException::class);

        SafeRemoteImage::fetch('https://1.1.1.1/sneaky.jpg');
    }

    public function test_it_rejects_a_server_error(): void
    {
        Http::fake([
            '1.1.1.1/*' => Http::response('Not found', 404),
        ]);

        $this->expectException(ValidationException::class);

        SafeRemoteImage::fetch('https://1.1.1.1/missing.jpg');
    }

    public function test_it_rejects_an_empty_body(): void
    {
        Http::fake([
            '1.1.1.1/*' => Http::response('', 200),
        ]);

        $this->expectException(ValidationException::class);

        SafeRemoteImage::fetch('https://1.1.1.1/empty.jpg');
    }

    /** The initial URL itself is validated before any request is made. */
    public function test_it_validates_before_making_a_request(): void
    {
        Http::fake();

        try {
            SafeRemoteImage::fetch('http://127.0.0.1/secret');
            $this->fail('Expected the private address to be rejected.');
        } catch (ValidationException) {
            // No request should have been issued.
        }

        Http::assertNothingSent();
    }

    /** Every request must identify itself honestly. */
    public function test_it_sends_a_descriptive_user_agent(): void
    {
        Http::fake([
            '1.1.1.1/*' => Http::response($this->pngBytes(), 200),
        ]);

        SafeRemoteImage::fetch('https://1.1.1.1/portrait.jpg');

        Http::assertSent(fn (Request $request) => str_contains(
            (string) $request->header('User-Agent')[0] ?? '',
            'image import'
        ));
    }
}
