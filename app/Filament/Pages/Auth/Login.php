<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Support\SiteSettings;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Passcode-only sign-in.
 *
 * There is exactly one person who needs this panel, so asking for an email
 * address as well as a secret bought nothing: there was no second identity to
 * disambiguate, and an email that is never used as a login identifier is one
 * more thing to keep current. The field is now a single passcode, stored
 * hashed in the same `password` column as before — no plaintext anywhere — and
 * changeable from Admin → My profile.
 *
 * ## What was deliberately NOT changed
 *
 * `getCredentialsFromFormData()` is the seam this design hangs on. It maps the
 * one submitted field back onto the {email, password} pair Filament's base
 * `authenticate()` expects, and resolves the email from the owner record. That
 * means the entire upstream authentication pipeline still runs unchanged:
 *
 *   - `Timebox` constant-duration padding, so a wrong passcode takes as long
 *     to reject as a right one and cannot be timed;
 *   - `isUserAllowedToAccessPanel()`, so role checks still apply;
 *   - the `attempting` / `failed` auth events;
 *   - the multi-factor challenge, if one is ever enabled.
 *
 * Only the form and the credential lookup are overridden. Rewriting
 * `authenticate()` wholesale would have meant re-implementing all of the above
 * by hand, and getting the padding or the panel-access check wrong is exactly
 * the kind of bug that is invisible until it matters.
 *
 * ## Rate limiting is unaffected
 *
 * `WithRateLimiting::getRateLimitKey()` hashes component + method + **IP** —
 * it never keyed on the email field, so removing that field does not weaken
 * the throttle. The escalating backoff below is unchanged from the previous
 * version of this class.
 *
 * ## Known trade-off
 *
 * A shared secret has no identity in it: whoever holds the passcode is signed
 * in as the owner, with owner rights. That is the intent while he is the only
 * user. If a second account is ever added (the schema already has an `editor`
 * role, and the policies test one), the passcode would still resolve to the
 * owner — so an editor given a passcode would silently be handed owner rights,
 * including deleting content and changing site settings. Before adding a second
 * person, switch this page back to email + password, or scope the passcode to a
 * specific user record rather than "the owner".
 */
class Login extends BaseLogin
{
    /**
     * A custom view rather than Filament's stock login, so the page carries
     * the same editorial identity as the public site instead of arriving as a
     * generic panel. Only static framing markup was added; the form itself is
     * still rendered by Filament's schema, so validation, error display and
     * focus behaviour are unchanged.
     */
    protected string $view = 'filament.pages.login';

    /** SECURITY.md §9 — attempts allowed per window. */
    public const MAX_ATTEMPTS = 5;

    /** Window (seconds) the attempt counter is measured over. */
    public const DECAY_SECONDS = 60;

    /**
     * Lockout ladder, in seconds, applied progressively to repeat offenders.
     * The last entry is reused for any further offence.
     */
    public const BACKOFF_SECONDS = [60, 300, 900];

    /** Offence counters expire after an hour of good behaviour. */
    public const OFFENCE_WINDOW_SECONDS = 3600;

    /**
     * One field. "Remember me" is intentionally absent: this is a single-admin
     * CMS where a long-lived unattended session is a liability, not a
     * convenience.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('passcode')
                    ->label('Passcode')
                    ->password()
                    ->revealable()
                    ->required()
                    ->autofocus()
                    ->autocomplete('current-password')
                    ->extraInputAttributes([
                        'autocapitalize' => 'off',
                        'autocorrect' => 'off',
                        'spellcheck' => 'false',
                    ])
                    ->helperText('The passcode set in Admin → My profile.'),
            ]);
    }

    /**
     * Map the single submitted field back onto the credentials Filament's base
     * `authenticate()` expects.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[\SensitiveParameter] array $data): array
    {
        return [
            // Resolved server-side and never accepted from the request. With no
            // owner on record this is null, `retrieveByCredentials()` returns
            // nothing, and the attempt fails closed.
            'email' => $this->owner()?->email,
            'password' => $data['passcode'] ?? '',
        ];
    }

    /**
     * The account the passcode unlocks.
     *
     * Deliberately the owner and not "the first user": the panel's most
     * dangerous capabilities belong to the owner role, so the passcode should
     * never resolve to a lesser one.
     */
    private function owner(): ?User
    {
        return User::where('role', 'owner')->first();
    }

    public function getHeading(): string
    {
        return 'Sign in';
    }

    public function getSubheading(): ?string
    {
        return SiteSettings::siteName().' — editorial admin';
    }

    public function getFormActionsAlignment(): string
    {
        return 'center';
    }

    public function hasFullWidthFormActions(): bool
    {
        return true;
    }

    public function getTitle(): string
    {
        return 'Sign in';
    }

    /**
     * SECURITY.md §9 — login throttle: 5 attempts/minute, then backoff.
     *
     * Filament's stock login page already throttles to 5 attempts per minute
     * (`WithRateLimiting::rateLimit(5)`), but a flat one-minute window means an
     * attacker can simply wait 60 seconds and start again. This page keeps the
     * spec'd 5/min window and adds an escalating lockout on top of it: each time
     * the limit is hit the block gets longer (1 min → 5 min → 15 min), so
     * sustained brute-forcing becomes progressively impractical while a legitimate
     * user who fat-fingers their passcode once is only ever delayed a minute.
     */
    protected function rateLimit($maxAttempts, $decaySeconds = 60, $method = null, $component = null)
    {
        // The trait normally derives these from a debug backtrace, which would
        // resolve to `rateLimit` itself once we override it — so pin them to
        // the real caller (`authenticate`) to keep the throttle key stable and
        // compatible with Filament's own limiter key.
        $method ??= 'authenticate';
        $component ??= static::class;

        if (! $this->isRateLimited($maxAttempts, $method, $component)) {
            $this->hitRateLimiter($method, $decaySeconds, $component);

            return;
        }

        // Over the limit: lengthen the window and record the offence.
        $lockout = $this->nextLockoutSeconds($component, $method);

        RateLimiter::hit($this->getRateLimitKey($method, $component), $lockout);

        throw new TooManyRequestsException($component, $method, request()->ip(), $lockout);
    }

    /**
     * Escalating lockout length for the offender's nth consecutive block.
     */
    private function nextLockoutSeconds(string $component, string $method): int
    {
        $offences = RateLimiter::increment(
            'login-backoff:'.sha1($component.'|'.$method.'|'.request()->ip()),
            self::OFFENCE_WINDOW_SECONDS
        );

        $index = min(max($offences, 1), count(self::BACKOFF_SECONDS)) - 1;

        return self::BACKOFF_SECONDS[$index];
    }
}
