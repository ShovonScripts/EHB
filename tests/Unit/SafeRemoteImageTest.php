<?php

namespace Tests\Unit;

use App\Support\SafeRemoteImage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Security tests for the "import image from URL" admin option.
 *
 * The feature fetches a URL on the server's behalf, which is a Server-Side
 * Request Forgery sink (CWE-918). These tests pin the denials.
 *
 * assertSafeUrl() is deliberately separated from the socket work so the rules
 * can be verified without a network — no DNS, no HTTP, no flakiness.
 */
class SafeRemoteImageTest extends TestCase
{
    /**
     * Providers and the code they attack.
     *
     * Each is a real published SSRF technique, not a hypothetical.
     *
     * @return array<string, array{0: string}>
     */
    public static function blockedUrls(): array
    {
        return [
            // Non-HTTP schemes. file:// reads local files; the rest are
            // protocol smuggling into handlers that were never meant to be
            // reachable from user input.
            'file scheme' => ['file:///c:/windows/win.ini'],
            'php wrapper' => ['php://filter/convert.base64-encode/resource=config/app.php'],
            'gopher smuggling' => ['gopher://127.0.0.1:6379/_FLUSHALL%0d%0a'],
            'dict protocol' => ['dict://127.0.0.1:11211/stat'],
            'ftp scheme' => ['ftp://example.com/x.jpg'],
            'data uri' => ['data:image/png;base64,iVBORw0KGgo='],
            'no scheme' => ['example.com/image.jpg'],
            'garbage' => ['not a url at all'],
            'empty' => [''],
            'whitespace only' => ['   '],

            // Loopback, in every spelling. None of these contain a hint of
            // "private" in the URL, which is exactly why hostname inspection
            // is not enough.
            'loopback ipv4' => ['http://127.0.0.1/'],
            'loopback ipv4 alt' => ['http://127.0.0.53/'],
            'localhost' => ['http://localhost/admin'],
            'loopback ipv6' => ['http://[::1]/'],

            // Link-local. 169.254.169.254 is the cloud instance metadata
            // service: reachable from inside the host, and it hands out
            // credentials to anything that asks.
            'cloud metadata' => ['http://169.254.169.254/latest/meta-data/iam/security-credentials/'],
            'link-local ipv6' => ['http://[fe80::1]/'],

            // RFC1918. Reaches other machines on the same network, including
            // databases and admin panels that trust the network position.
            '10/8' => ['http://10.0.0.5/'],
            '172.16/12' => ['http://172.16.0.1/'],
            '192.168/16' => ['http://192.168.1.1/'],
            'ipv6 unique local' => ['http://[fc00::1]/'],

            // Unspecified / reserved, which can be treated as loopback.
            '0.0.0.0' => ['http://0.0.0.0/'],
            'broadcast' => ['http://255.255.255.255/'],
            'multicast' => ['http://224.0.0.1/'],

            // Credential leakage in logs and in the referrer chain.
            'user:pass in url' => ['http://admin:hunter2@example.com/img.jpg'],
        ];
    }

    #[DataProvider('blockedUrls')]
    public function test_it_rejects_unsafe_urls(string $url): void
    {
        $this->expectException(ValidationException::class);

        SafeRemoteImage::assertSafeUrl($url);
    }

    public function test_it_rejects_an_overlong_url(): void
    {
        $this->expectException(ValidationException::class);

        SafeRemoteImage::assertSafeUrl('http://example.com/'.str_repeat('a', 2100).'.jpg');
    }

    /**
     * A hostname that resolves to a private address must be rejected.
     *
     * This is the case a naive check misses: the URL contains no IP at all and
     * reads like an ordinary public site, but "localtest.me" is a public DNS
     * name that resolves to 127.0.0.1 by design.
     */
    public function test_it_rejects_a_public_hostname_that_resolves_to_a_private_address(): void
    {
        // Skipped rather than silently passed when DNS is unavailable, so an
        // offline run does not report a false green.
        if (! @gethostbynamel('localtest.me')) {
            $this->markTestSkipped('localtest.me did not resolve (no DNS in this environment).');
        }

        $this->expectException(ValidationException::class);

        SafeRemoteImage::assertSafeUrl('http://localtest.me/admin');
    }

    public function test_it_rejects_a_host_that_does_not_resolve(): void
    {
        $this->expectException(ValidationException::class);

        // .invalid is reserved by RFC 2606 and never resolves, so this is
        // deterministic offline.
        SafeRemoteImage::assertSafeUrl('http://this-host-does-not-exist.invalid/img.jpg');
    }

    /** A genuinely public address is allowed through the IP gate. */
    public function test_it_permits_public_ip_literals(): void
    {
        $this->assertSame('https://1.1.1.1/img.jpg', SafeRemoteImage::assertSafeUrl('https://1.1.1.1/img.jpg'));
        $this->assertSame('https://8.8.8.8/img.jpg', SafeRemoteImage::assertSafeUrl('https://8.8.8.8/img.jpg'));
    }

    /** http and https are the only schemes allowed, and both must survive. */
    public function test_it_permits_http_and_https(): void
    {
        $this->assertStringContainsString('http://', SafeRemoteImage::assertSafeUrl('http://1.1.1.1/a.jpg'));
        $this->assertStringContainsString('https://', SafeRemoteImage::assertSafeUrl('https://1.1.1.1/a.jpg'));
    }

    /** The cap is 10 MB, matching the upload ceiling. */
    public function test_the_size_cap_is_ten_megabytes(): void
    {
        $this->assertSame(10 * 1024 * 1024, SafeRemoteImage::MAX_BYTES);
    }
}
