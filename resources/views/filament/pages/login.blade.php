{{--
    Redesigned sign-in page.

    `x-filament-panels::page.simple` is kept rather than replaced, because it
    is what renders the heading, the subheading and Filament's action modals.
    Only static framing was added around `{{ $this->content }}` — the form,
    its validation and its error display are still Filament's, so none of that
    behaviour was reimplemented by hand.

    Styling lives in resources/css/admin.css under `.ehb-login-*`.
--}}
<x-filament-panels::page.simple>
    <div class="ehb-login">
        {{-- Wordmark. `brandName` already reads the admin-editable site name;
             this repeats it as the masthead so the page opens with whose
             archive this is, which is also the answer to "which site is this
             login for?" on a shared machine. --}}
        <div class="ehb-login__masthead">
            <span class="ehb-login__rule" aria-hidden="true"></span>
            <p class="ehb-login__wordmark">{{ \App\Support\SiteSettings::siteName() }}</p>
            <p class="ehb-login__tagline">
                {{ \App\Models\JournalistProfile::current()?->title ?? 'Editorial admin' }}
            </p>
        </div>

        {{-- Filament renders the heading, subheading, the passcode field and
             the submit button here. --}}
        {{ $this->content }}

        <p class="ehb-login__note">
            Single-administrator panel. Change the passcode any time from
            <span class="ehb-login__note-strong">My profile</span> once you are inside.
        </p>
    </div>
</x-filament-panels::page.simple>
