# SECURITY.md

## 1. Authentication

Filament's shipped auth pipeline, driven from `App\Filament\Pages\Auth\Login`.

**Sign-in is a single passcode with no identity field.** There is one person who
needs this panel, so the form asks for one secret rather than an email *and* a
password: there was no second identity to disambiguate, and an email that is
never used as a login identifier is one more thing to keep current. The field is
called a **passcode** throughout the UI, including on the change form at
`/admin/profile`.

What this does **not** change:

- The passcode is stored bcrypt-hashed in the same `users.password` column.
  There is no plaintext passcode anywhere, including in the environment.
- The account is resolved **server-side** from `role = 'owner'`. The email is
  never read from the request, so a submitted `email` cannot redirect the login
  at a different account. The passcode deliberately resolves to the *owner* and
  not "the first user", so it can never escalate into a lesser role.
- Filament's whole upstream `authenticate()` still runs: `Timebox`
  constant-duration padding (a wrong passcode takes as long to reject as a
  right one, so it cannot be timed), `canAccessPanel()` role checks, the
  attempting/failed auth events, and the multi-factor challenge if ever enabled.
  `Login::getCredentialsFromFormData()` is the only seam overridden — it maps
  the one submitted field back onto the `{email, password}` pair the base class
  expects.
- The passcode is changeable at `/admin/profile`, which additionally requires
  the **current** passcode before saving a new one.

### Known trade-off, and when to revisit

A shared secret carries no identity: whoever holds the passcode is signed in as
the **owner**, with owner rights. That is the intent while one person runs the
site, but it is a real constraint rather than a neutral simplification:

- The schema already models a second role (`users.role = 'editor'`) and the
  policies test one. **Before adding a second person, this page must go back to
  email + password, or the passcode must be scoped to a specific user record
  instead of "the owner."** Otherwise an editor given a passcode is silently
  handed owner rights, including deleting content and changing every setting.
- Passcodes resist guessing far worse than passwords do by volume: a 20-character
  alphanumeric secret is fine, but a short or memorable one is not. The throttle
  (§9) is the only thing standing between a weak passcode and an online
  guessing attack, so do not weaken it.
- Two-factor authentication is the recommended next step for this account,
  given the reputational stakes of a journalist's panel being compromised. It is
  supported by the Filament pipeline already in place and was deliberately not
  removed when the form was simplified.

## 2. Authorization

Filament policies gate all admin resources to authenticated users with `role = owner` (and, later, `editor` with narrower permissions). No public registration route for admin accounts — accounts created manually/by seeding only.

## 3. Passcode Security

- Bcrypt hashing (Laravel default). The passcode is a password in every respect
  except its label.
- Minimum length/complexity enforced when the passcode is changed
  (Filament's `Password::default()` rule is still applied).
- Rate-limited sign-in attempts, throttled per IP with escalating backoff
  (§9). The throttle key is `sha1(component|method|IP)` and never included the
  email field, so simplifying the form did not widen the window.
- The passcode is generated once at creation, printed once, and never
  recoverable — there is deliberately no default value to guess
  (see `database/seeders/AdminUserSeeder.php`).
- Recommended: two-factor authentication, given the reputational stakes.

## 4. Admin Security

- Admin panel served at a non-obvious but not security-by-obscurity-reliant path; real protection comes from auth + rate limiting, not path secrecy.
- Session timeout after inactivity.
- Consider IP-based login alerting (email notification on new-device login) as a low-cost future addition.

## 5. CSRF

All admin and public forms (including the Contact form) protected by Laravel's CSRF token middleware.

## 6. XSS

- All user-supplied output escaped by default (Blade's `{{ }}` auto-escaping).
- Rich-text body content (Internal Articles) sanitized on save (strip script tags/event handlers) using a server-side HTML sanitizer, not just trusting the WYSIWYG editor's client-side behavior.
- Contact form inputs escaped when displayed in the admin inbox.

## 7. SQL Injection Prevention

Exclusive use of Eloquent ORM / query builder with parameter binding; no raw string-concatenated queries. Search functionality (§ full-text/LIKE queries) also uses bound parameters.

## 8. Mass Assignment

All Eloquent models define explicit `$fillable` (or `$guarded` where more appropriate) — especially `content_items`, so a crafted request can never set `status = published` or reassign `author_id` outside of an authorized admin action.

## 9. Rate Limiting

- Login endpoint: strict throttle (e.g., 5 attempts/minute with backoff).
- Contact form: throttled per IP to prevent spam flooding, plus a honeypot field and/or lightweight CAPTCHA if spam becomes an issue post-launch.
- Search endpoint: light throttle to prevent abuse/DoS via expensive queries.

## 10. File Upload Security

- Media library uploads restricted by MIME type allowlist per media type (images: jpg/png/webp; documents: pdf).
- File size limits enforced server-side.
- Uploaded files stored outside the public web-executable path where possible, served via a controlled route or a storage disk configured to prevent script execution.
- Filenames sanitized/randomized on storage to avoid path traversal or overwrite attacks.

## 11. Image Validation

- Re-encode/validate uploaded images server-side (not just trusting file extension) to prevent disguised-payload uploads (e.g., a script renamed `.jpg`).
- Strip EXIF metadata on upload (privacy — avoids leaking geolocation/device data from source photos).

## 12. Document Upload Security

- PDFs/reports scanned for embedded scripts where feasible; served with `Content-Disposition` headers appropriate to force download vs. inline render as needed.
- Document uploads restricted to the journalist's own admin account — no public upload surface exists in V1.

## 13. Secure External URLs

- External Work `external_url` validated as a well-formed absolute URL before save.
- Outbound links rendered with `rel="noopener noreferrer"`.
- Optional (future): periodic link-check job to flag dead external URLs for the journalist to review, rather than silently 404ing for readers.

### 13.1 Importing an image from a URL (SSRF)

The admin can import an image by pasting a URL instead of uploading a file. The
**server** performs that fetch, which is a Server-Side Request Forgery sink
(CWE-918) — the classic payloads are the cloud metadata endpoint
(`169.254.169.254`), loopback admin panels, and anything else reachable only
from inside the network. The URL is therefore treated as hostile input and
validated before a socket is opened (`app/Support/SafeRemoteImage.php`):

| Check | Blocks |
| --- | --- |
| Scheme allowlist, `http`/`https` only | `file://` (local file read), `php://` wrappers, `gopher://`/`dict://` protocol smuggling |
| No credentials in the URL | `user:pass@host` leaking into logs and referrers |
| Resolved IPs must be public | Loopback (including `localhost` and `[::1]`), RFC1918, link-local, `0.0.0.0`, IPv6 ULA, multicast |
| Redirects followed manually, each hop re-validated | A public URL that 302s to a private address |
| Bounded time (20s total) and size (10 MB, counted while streaming) | A URL that hangs a worker or streams an unbounded body |
| MIME sniffed from the bytes, never the `Content-Type` header | A server claiming `image/jpeg` while sending HTML |
| Bytes re-run through `SecureUpload::sanitize()` | SVG, polyglots, oversized dimensions, EXIF leakage; the URL route is not a weaker door than the upload route |

Redirects are the reason `allow_redirects` is off: a client that follows them
automatically performs no checks on hops 2..n, which is the standard way SSRF
filters are bypassed.

The image is **downloaded and stored on our own disk** rather than hot-linked.
Storing the remote URL would avoid SSRF but would make every reader depend on a
third party, hand that party a request per visitor, break against hotlink
protection (already encountered with the outlet's own images), and bypass
`SecureUpload` entirely. An imported image is byte-for-byte equivalent to an
uploaded one, and no origin URL is retained anywhere on the `Media` row.

**Residual risk, stated plainly:** the IP checks run once per hop, immediately
before the request, so an attacker controlling a DNS zone could in principle
return a public IP for the check and a private one for the connection (DNS
rebinding). Closing that fully means pinning the socket to the validated IP,
which risks breaking TLS certificate validation for every legitimate import. Not
done deliberately: the panel has a single trusted admin who would have to be
induced to paste an attacker-supplied URL. Revisit if the panel ever gains more
than one admin.

## 14. Content Sanitization

Covered under XSS (§6); applies uniformly to any field that renders as HTML (article body, bio, career/award descriptions if rich text is allowed there).

## 15. Activity Logs

The `activity_logs` table (see `DATABASE.md`) records create/update/publish/delete actions with user, timestamp, and a diff/summary of changes — useful for accountability now and essential once a second contributor/editor role exists.

## 16. Backups

- Automated daily database backups, retained on a rolling window (e.g., 30 days), stored off-server (e.g., separate storage bucket).
- Media library backed up on the same or a compatible schedule.
- Periodic restore-test recommended (documented in `ROADMAP.md` Phase 9/10).

## 17. Secrets / Environment Variables

- All credentials (DB, mail, storage, third-party keys) in `.env`, never committed to version control.
- `.env.example` maintained without real values.
- Production secrets managed via the hosting platform's secret store, not plain files where avoidable.

## 18. Production Security

- HTTPS enforced site-wide (HSTS).
- Debug mode (`APP_DEBUG`) forced off in production, with generic error pages shown to visitors and detailed errors only logged server-side.
- Regular dependency updates (Composer/npm) to patch known vulnerabilities.

## 19. Security Headers

Content-Security-Policy (restrictive, allowing only needed script/style/image sources), X-Content-Type-Options: nosniff, X-Frame-Options: DENY (or frame-ancestors in CSP), Referrer-Policy: strict-origin-when-cross-origin, Permissions-Policy limiting unused browser features.

## 20. Future Consideration: Confidential Source Submissions / Whistleblower Intake

**This is explicitly out of scope for V1** and must not be improvised as a bolt-on contact form. A journalist accepting confidential tips carries real safety implications for sources, and a half-built version is worse than none.

If pursued in a future version, it would require (not built now, documented for later):
- A properly reviewed, purpose-built secure submission channel (e.g., an established platform like SecureDrop, or a bespoke system built with encryption-at-rest, minimal metadata retention, and ideally Tor-accessibility) rather than a standard web form.
- Legal consultation on data retention, source-protection obligations, and jurisdictional risk in Bangladesh.
- Infrastructure separation from the main marketing/portfolio site (a compromise of the portfolio site should not expose source-submission data).
- A clear policy on what metadata is/isn't logged (IP logging would defeat the purpose).
- This is a specialized undertaking that should involve security professionals experienced in journalist source-protection tooling before any implementation begins — it is not something to add incrementally to the CMS described in this document set.
