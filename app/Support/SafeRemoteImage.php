<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Fetch an image from a remote URL for the admin's "import from URL" option.
 *
 * ## Why this class exists rather than a plain file_get_contents
 *
 * Fetching a URL the *user* supplied means the server, not the browser, makes
 * the request. That is a Server-Side Request Forgery primitive: the classic
 * payloads are cloud metadata endpoints (169.254.169.254), loopback admin
 * panels, and anything else only reachable from inside the network. A URL field
 * that naively fetches its input is a way to read the private network, and it
 * is a well-known class of bug (CWE-918).
 *
 * So the URL is treated as hostile input and checked before any socket is
 * opened. Every one of these is load-bearing:
 *
 *  - **Scheme allowlist.** Only http/https. This blocks `file://` (read local
 *    files), `gopher://` and `dict://` (protocol smuggling), and `php://`
 *    wrappers.
 *  - **No credentials in the URL.** `http://user:pass@host/` leaks the
 *    credentials in logs and is a phishing shape.
 *  - **No private, loopback, link-local or reserved IPs.** Checked against the
 *    *resolved* addresses, not the hostname, because `http://127.0.0.1/` and
 *    `http://localhost/` and `http://[::1]/` all reach the loopback interface
 *    and none of them contain the word "private" in the URL.
 *  - **Redirects are followed manually and re-validated.** Otherwise a public
 *    URL that 302s to `http://169.254.169.254/latest/meta-data/` walks straight
 *    past every check above. Each hop goes through the identical validation.
 *  - **Bounded time and size.** A URL that streams forever, or a body far
 *    larger than any image we accept, must not be able to hold a worker.
 *  - **Content sniffing, never the Content-Type header.** The server sending the
 *    bytes is not trusted to describe them. The sniffed type is what matters,
 *    and it is re-verified by SecureUpload.
 *
 * ## Residual risk, stated plainly
 *
 * The IP checks run once per redirect hop, immediately before the request. An
 * attacker who controls a DNS zone could return a public IP for the pre-check
 * and a private one for the connection (DNS rebinding). Closing that fully means
 * pinning the socket to the validated IP. That is deliberately not done here:
 * this panel has exactly one trusted user, who would have to be induced to paste
 * an attacker-supplied URL, and pinning risks breaking TLS SNI/certificate
 * validation for every legitimate import. The checks above stop every realistic
 * and self-inflicted case; a determined rebinding attack against a single-admin
 * panel is not the threat this is protecting against. If the panel ever gains
 * more than one admin, revisit this.
 *
 * ## Why we download rather than store the remote URL
 *
 * Storing the URL and rendering `<img src="{remote}">` avoids SSRF entirely,
 * but it is the wrong trade here:
 *  - The Daily Star blocks hotlinking — already encountered, which is why the
 *    outlet's own image URLs are not directly usable.
 *  - It makes every reader's browser depend on a third party, and hands that
 *    party a request from each visitor (a tracking pixel).
 *  - It bypasses SecureUpload entirely: no EXIF strip, no re-encode, no
 *    dimension bound, no mime verification.
 *  - Media.file_path assumes a file on our own disk; a remote URL breaks the
 *    root-relative URL scheme and the WebP derivative pipeline.
 *
 * Downloading once, at import, keeps every downstream guarantee intact.
 */
class SafeRemoteImage
{
    /**
     * Ceiling on the downloaded body.
     *
     * Matches the image ceiling in SecureUpload (10 MB) so an image that would
     * be rejected on upload is not pulled down the wire first.
     */
    public const MAX_BYTES = 10 * 1024 * 1024;

    /**
     * Total seconds for the whole import, redirects included.
     *
     * This is a *transfer* budget, not a reachability one — it has to be
     * generous, because a legitimate 10 MB image on a slow link needs real
     * time and failing it spuriously is worse than waiting. Reachability is
     * governed separately by CONNECT_TIMEOUT_SECONDS, which is short.
     *
     * The two were previously one number, which meant the only way to make a
     * dead host fail faster was to also make large legitimate downloads fail
     * faster. They are now distinct; see fetch().
     */
    public const TIMEOUT_SECONDS = 20;

    /**
     * Seconds allowed to establish the connection.
     *
     * Kept short on purpose. When a host is dead, unroutable, or silently
     * dropping packets, essentially all of the wait is here — the connection
     * never completes, so no bytes ever arrive and no progress is possible.
     * That is the case an admin waits through when they paste a bad URL, and
     * it is bounded tightly so it reads as "that didn't work" rather than as a
     * hung page.
     */
    public const CONNECT_TIMEOUT_SECONDS = 5;

    /** Redirect hops permitted before giving up. */
    public const MAX_REDIRECTS = 3;

    /**
     * Validate a URL and return it in normalized form, or fail.
     *
     * Split out from the fetch so the security rules are testable without a
     * network, and so every redirect hop can be run through the same gate.
     *
     * @throws ValidationException
     */
    public static function assertSafeUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            throw self::reject('Enter an image URL.');
        }

        if (strlen($url) > 2048) {
            throw self::reject('That URL is too long.');
        }

        $parts = parse_url($url);

        // parse_url returns false on badly malformed input, and an array with
        // no host for things like "http:///path" or a bare "not a url".
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw self::reject('That does not look like a valid URL.');
        }

        $scheme = Str::lower($parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw self::reject('Only http:// and https:// URLs are supported.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw self::reject('URLs containing a username or password are not allowed.');
        }

        $host = $parts['host'];

        // IPv6 literals arrive bracketed from parse_url.
        $literal = trim($host, '[]');

        if (filter_var($literal, FILTER_VALIDATE_IP)) {
            self::assertPublicIp($literal);

            return $url;
        }

        // A hostname. Resolve it and check every answer, not just the first:
        // a name with one public and one private A record must be rejected,
        // because which one gets used is not ours to decide.
        $addresses = self::resolve($host);

        if ($addresses === []) {
            throw self::reject('That host could not be resolved.');
        }

        foreach ($addresses as $address) {
            self::assertPublicIp($address);
        }

        return $url;
    }

    /**
     * Reject anything that is not a routable public address.
     *
     * FILTER_FLAG_NO_PRIV_RANGE covers 10/8, 172.16/12, 192.168/16, fc00::/7.
     * FILTER_FLAG_NO_RES_RANGE covers loopback (127/8, ::1), link-local
     * (169.254/16, fe80::/10 — which is where the cloud metadata service
     * lives), 0.0.0.0, and the rest of the reserved blocks. Both flags are
     * required: NO_RES_RANGE alone would let 192.168.1.1 through.
     */
    private static function assertPublicIp(string $ip): void
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw self::reject('That URL points to a private or reserved network address, which is not allowed.');
        }

        // FILTER_FLAG_NO_RES_RANGE does not cover 224.0.0.0/4. Multicast is not
        // a useful data-exfiltration target, but it is plainly not a legitimate
        // image host either, and rejecting it costs nothing.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $octets = array_map('intval', explode('.', $ip));

            if ($octets[0] >= 224 && $octets[0] <= 239) {
                throw self::reject('That URL points to a multicast address, which is not allowed.');
            }
        }
    }

    /**
     * Resolve a hostname to every address it answers with, IPv4 and IPv6.
     *
     * gethostbynamel() only returns A records, so a v6-only host would look
     * unresolvable — and an attacker could lean on that. dns_get_record() is
     * consulted for AAAA alongside it.
     *
     * @return list<string>
     */
    private static function resolve(string $host): array
    {
        $addresses = @gethostbynamel($host);

        if (is_array($addresses)) {
            $addresses = array_values(array_filter($addresses, 'is_string'));
        } else {
            $addresses = [];
        }

        $v6 = @dns_get_record($host, DNS_AAAA);

        if (is_array($v6)) {
            foreach ($v6 as $record) {
                if (isset($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        return $addresses;
    }

    /**
     * Download an image, following redirects safely.
     *
     * @param  int|null  $timeout  total seconds for the whole transfer,
     *                             redirects included. Defaults to
     *                             TIMEOUT_SECONDS; pass a lower value from an
     *                             interactive caller that cannot afford to
     *                             block the request it is running inside.
     * @return array{contents: string, mime: string}
     *
     * @throws ValidationException
     */
    public static function fetch(string $url, ?int $timeout = null): array
    {
        $timeout ??= self::TIMEOUT_SECONDS;

        // The deadline is the total budget for every hop combined, not per
        // hop, so a chain of slow redirects cannot multiply the wait.
        $deadline = microtime(true) + $timeout;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $url = self::assertSafeUrl($url);

            try {
                $response = Http::withOptions([
                    // Redirects are handled here so each hop is re-validated.
                    // Letting Guzzle follow them automatically would skip the
                    // IP checks on every hop after the first.
                    'allow_redirects' => false,
                    'stream' => true,
                ])
                    ->withHeaders([
                        'User-Agent' => 'ProDo/EHB image import (+admin panel)',
                        'Accept' => 'image/*',
                    ])
                    ->timeout($timeout)
                    // Never longer than the total: a connect timeout above it
                    // would be unreachable, since the total gives up first.
                    ->withOptions([
                        'connect_timeout' => min(self::CONNECT_TIMEOUT_SECONDS, $timeout),
                    ])
                    ->get($url);
            } catch (ConnectionException $e) {
                Log::warning('Remote image import could not connect', ['url' => $url]);

                throw self::reject('Could not reach that URL.');
            }

            if ($response->redirect()) {
                $location = $response->header('Location');

                if ($location === '') {
                    throw self::reject('That URL redirected without saying where to.');
                }

                // Resolve relative redirects (Location: /img.jpg) against the
                // current URL, as a browser would.
                $url = self::resolveRedirect($url, $location);
                $url = self::assertSafeUrl($url);

                continue;
            }

            if (! $response->successful()) {
                throw self::reject('That URL returned HTTP '.$response->status().'.');
            }

            $declaredLength = $response->header('Content-Length');

            if ($declaredLength !== '' && (int) $declaredLength > self::MAX_BYTES) {
                throw self::reject('That image is larger than '.self::describeLimit().'.');
            }

            $contents = self::readBounded($response, $url, $deadline);

            return [
                'contents' => $contents,
                'mime' => self::sniff($contents),
            ];
        }

        throw self::reject('That URL redirected too many times.');
    }

    /**
     * Read the streamed body, aborting the moment it exceeds the cap.
     *
     * A Content-Length header is a claim by an untrusted server and is only used
     * to fail fast. The real enforcement is counting bytes as they arrive, so a
     * server that understates (or omits) its length cannot stream 2 GB into
     * memory.
     */
    private static function readBounded(Response $response, string $url, float $deadline): string
    {
        $body = $response->toPsrResponse()->getBody();
        $body->rewind();

        $buffer = '';
        $total = 0;

        while (! $body->eof()) {
            if (microtime(true) > $deadline) {
                throw self::reject('That URL took too long to respond.');
            }

            $chunk = $body->read(65536);

            if ($chunk === '') {
                continue;
            }

            $total += strlen($chunk);

            if ($total > self::MAX_BYTES) {
                // Stop the transfer rather than just discarding the excess.
                $body->close();

                throw self::reject('That image is larger than '.self::describeLimit().'.');
            }

            $buffer .= $chunk;
        }

        if ($buffer === '') {
            throw self::reject('That URL returned an empty response.');
        }

        return $buffer;
    }

    /**
     * Sniff the MIME type from the bytes, ignoring what the server claimed.
     */
    private static function sniff(string $contents): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($contents);

        if (! in_array($mime, SecureUpload::IMAGE_MIME_TYPES, true)) {
            throw self::reject(
                'That URL is '.($mime ?: 'an unrecognised type').', not a JPEG, PNG, WebP or GIF.'
            );
        }

        return $mime;
    }

    /** Resolve a possibly-relative Location header against the URL it came from. */
    private static function resolveRedirect(string $base, string $location): string
    {
        if (parse_url($location, PHP_URL_SCHEME) !== null) {
            return $location;
        }

        $parts = parse_url($base);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return $location;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        if (str_starts_with($location, '//')) {
            return $parts['scheme'].':'.$location;
        }

        return $origin.'/'.ltrim($location, '/');
    }

    private static function describeLimit(): string
    {
        return (self::MAX_BYTES / 1024 / 1024).' MB';
    }

    private static function reject(string $message): ValidationException
    {
        return ValidationException::withMessages(['url' => $message]);
    }
}
