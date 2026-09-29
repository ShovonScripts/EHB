@props([
    'title' => null,
    'description' => null,
    'canonical' => null,
    'ogImage' => null,
    'ogType' => 'website',
    'noindex' => false,
    'jsonLd' => null,
    'breadcrumbs' => null,
    'ogImageAlt' => null,
])

@php
    $siteName = \App\Support\SiteSettings::siteName();

    // SEO.md §1 — bounded so the site name is not pushed off the end of a
    // search result. See App\Support\MetaTitle for why this needs a budget
    // rather than plain concatenation: 75% of this site's long outlet
    // headlines overflowed the SERP. The visible <h1> is untouched.
    $metaTitle = \App\Support\MetaTitle::compose(
        $title,
        $siteName,
        \App\Support\SiteSettings::siteTagline(),
    );

    $metaDescription = $description ?? \App\Support\SiteSettings::defaultSeoDescription();

    // SEO.md §2 — per-entity image, else the site-wide default from Settings.
    //
    // Media URLs are root-relative (see tests/Feature/MediaUrlTest.php: an
    // absolute URL built from APP_URL pointed images at a different origin when
    // the site was browsed anywhere else, and every image 404'd). Social cards
    // are the exception: Open Graph and Twitter images are fetched by crawlers
    // that resolve them against nothing, so they must be absolute. Absolutise
    // here, once, rather than at each call site.
    $ogImageUrl = $ogImage ?: \App\Support\SiteSettings::defaultOgImageUrl();

    if ($ogImageUrl !== null && str_starts_with($ogImageUrl, '/')) {
        $ogImageUrl = url($ogImageUrl);
    }

    $canonicalUrl = $canonical ?? url()->current();
@endphp

{{-- SEO.md §1–§5 — titles, meta, canonical, OG, Twitter cards --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $metaTitle }}</title>

    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">

    {{-- A page-level noindex (search, filtered archive) or the site-wide switch
         from Admin → Settings. Either one is enough; noindex wins. --}}
    @if($noindex || ! \App\Support\SiteSettings::isIndexable())
        <meta name="robots" content="noindex,follow">
    @endif

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

    {{-- Google Search Console ownership. Emitted verbatim — the value is the
         token from the verification meta tag, not a URL. --}}
    @if($verification = \App\Support\SiteSettings::googleSiteVerification())
        <meta name="google-site-verification" content="{{ $verification }}">
    @endif

    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    @if($ogImageUrl)
        <meta property="og:image" content="{{ $ogImageUrl }}">
        {{-- Describes the share card for anyone reading it with a screen
             reader. Falls back to the piece's own image alt text. --}}
        <meta property="og:image:alt" content="{{ \App\Support\SiteSettings::defaultOgImageAlt() ?? $ogImageAlt ?? $metaTitle }}">
    @endif

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $metaTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    @if($ogImageUrl)
        <meta name="twitter:image" content="{{ $ogImageUrl }}">
    @endif
    @if($handle = \App\Support\SiteSettings::twitterHandle())
        {{-- twitter:site is the site the card belongs to; twitter:creator is
             the author. Both are the same person on a single-author site.

             The `@` is prepended inside the expression rather than written as
             `@{{ $handle }}` next to the echo: an `@` immediately before a
             Blade echo stops it being compiled, and the tags rendered the
             literal text `{{ $handle }}` to readers. --}}
        <meta name="twitter:site" content="{{ '@'.$handle }}">
        <meta name="twitter:creator" content="{{ '@'.$handle }}">
    @endif

    {{-- DESIGN_SYSTEM.md §2 — Libre Baskerville + Inter, Noto pair for Bangla --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&family=Noto+Sans+Bengali:wght@400;500;600;700&family=Noto+Serif+Bengali:wght@400;600;700&display=swap" rel="stylesheet">

    {{-- SEO.md §10 — feed autodiscovery --}}
    <link rel="alternate" type="application/rss+xml" title="{{ $siteName }}" href="{{ url('/feed') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @if($jsonLd)
        {{-- The one inline script on the public site. The nonce is a
             PLACEHOLDER, not a real value: this body may be stored by
             CachePublicResponses and replayed to a later request, and a
             per-request nonce baked into a cached body would no longer match
             the policy that authorises it — the browser would silently refuse
             to run the block, killing the structured data with no visible
             error. SecurityHeaders substitutes the real nonce on the way out,
             after the cache has had its copy. See Csp::PLACEHOLDER. --}}
        <script type="application/ld+json" nonce="{{ \App\Support\Csp::PLACEHOLDER }}">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif

    @stack('head')
</head>
<body class="flex min-h-screen flex-col antialiased">
    <a href="#main-content" class="skip-link">Skip to content</a>

    {{-- Shared Header/Nav — FRONTEND.md §3 --}}
    <header class="sticky top-0 z-40 border-b border-hairline bg-paper/95 backdrop-blur-sm">
        <div class="container-wide flex h-16 items-center justify-between">
            <a href="{{ route('home') }}" class="font-serif text-lg font-bold tracking-tight text-ink transition-colors hover:text-accent">
                {{ \App\Models\JournalistProfile::current()?->name ?? $siteName }}
            </a>

            <nav class="hidden items-center gap-6 text-sm font-medium lg:flex" aria-label="Primary">
                @php
                    // [label, route name, active path prefix]. On-site sections
                    // (Articles, Investigations, ...) only appear once they have
                    // hosted work — see HostedSections.
                    $navLinks = [
                        ['Reporting', 'work.index', 'work'],
                        ['Sections', 'sections.index', 'sections'],
                        ['Beats', 'topics.index', 'topics'],
                    ];

                    foreach ($hostedSections ?? [] as $key => $section) {
                        $navLinks[] = [$section['label'], 'section.'.$key, $key];
                    }

                    $navLinks[] = ['Archive', 'archive', 'archive'];
                    $navLinks[] = ['Publications', 'publications.index', 'publications'];
                    $navLinks[] = ['About', 'about', 'about'];
                    $navLinks[] = ['Contact', 'contact.create', 'contact'];
                @endphp

                @foreach($navLinks as [$label, $routeName, $prefix])
                    <a href="{{ route($routeName) }}"
                       @class([
                           'transition-colors hover:text-accent',
                           'text-accent' => request()->is($prefix) || request()->is($prefix.'/*'),
                           'text-muted' => ! request()->is($prefix) && ! request()->is($prefix.'/*'),
                       ])>{{ $label }}</a>
                @endforeach

                <a href="{{ route('search') }}" class="text-muted transition-colors hover:text-accent" aria-label="Search">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.34-4.34M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z"/></svg>
                </a>
            </nav>

            <button type="button" data-nav-toggle aria-expanded="false" aria-label="Open menu" aria-controls="mobile-nav-menu" class="-mr-2 p-2 text-ink lg:hidden">
                <span class="icon-open">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                </span>
                <span class="icon-close">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </span>
            </button>
        </div>

        <nav data-nav-menu id="mobile-nav-menu" class="nav-panel hidden border-t border-hairline bg-paper lg:hidden" aria-label="Mobile">
            <div class="container-wide flex flex-col py-3">
                @foreach($navLinks as [$label, $routeName, $prefix])
                    <a href="{{ route($routeName) }}"
                       @class([
                           'border-b border-hairline/60 py-3',
                           'text-accent' => request()->is($prefix) || request()->is($prefix.'/*'),
                           'text-muted hover:text-accent' => ! request()->is($prefix) && ! request()->is($prefix.'/*'),
                       ])>{{ $label }}</a>
                @endforeach
                <a href="{{ route('search') }}" class="py-3 text-muted hover:text-accent">Search</a>
            </div>
        </nav>
    </header>

    @if($breadcrumbs ?? null)
        <div class="border-b border-hairline bg-paper">
            <div class="container-wide py-3">
                <x-breadcrumbs :crumbs="$breadcrumbs" />
            </div>
        </div>
    @endif

    <main id="main-content" tabindex="-1" class="flex-1">
        {{ $slot }}
    </main>

    {{-- DESIGN_SYSTEM.md §11 — footer w/ disclaimer --}}
    <footer class="mt-20 border-t border-hairline bg-paper">
        <div class="container-wide py-12">
            @php($footerProfile = \App\Models\JournalistProfile::current()?->load('publications'))

            <div class="grid gap-10 md:grid-cols-3">
                <div>
                    <p class="font-serif text-lg font-bold text-ink">{{ $footerProfile->name ?? $siteName }}</p>
                    @if($footerProfile?->short_bio)
                        <p class="mt-2 text-sm leading-relaxed text-muted">{{ \Illuminate\Support\Str::limit($footerProfile->short_bio, 180) }}</p>
                    @endif
                    @if($footerProfile?->social_links)
                        <div class="mt-4 flex flex-wrap gap-4 text-sm">
                            @foreach(['twitter' => 'X / Twitter', 'linkedin' => 'LinkedIn', 'facebook' => 'Facebook'] as $key => $label)
                                @if(!empty($footerProfile->social_links[$key]))
                                    <a href="{{ $footerProfile->social_links[$key] }}" rel="noopener noreferrer" target="_blank" class="link-accent">{{ $label }}</a>
                                @endif
                            @endforeach
                            @if(!empty($footerProfile->social_links['email']))
                                <a href="mailto:{{ $footerProfile->social_links['email'] }}" class="link-accent">Email</a>
                            @endif
                        </div>
                    @endif
                </div>

                <div>
                    <h2 class="eyebrow">Browse</h2>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li><a href="{{ route('work.index') }}" class="text-muted hover:text-accent">Reporting</a></li>
                        <li><a href="{{ route('sections.index') }}" class="text-muted hover:text-accent">Sections</a></li>
                        <li><a href="{{ route('topics.index') }}" class="text-muted hover:text-accent">Beats</a></li>
                        <li><a href="{{ route('archive') }}" class="text-muted hover:text-accent">Full Archive</a></li>
                        <li><a href="{{ route('publications.index') }}" class="text-muted hover:text-accent">Publications</a></li>
                    </ul>
                </div>

                <div>
                    <h2 class="eyebrow">As Seen In</h2>
                    <ul class="mt-3 space-y-2 text-sm">
                        @forelse($footerProfile->publications ?? [] as $pub)
                            <li>
                                <a href="{{ route('publications.show', $pub->slug) }}" class="text-muted hover:text-accent">
                                    {{ $pub->name }}
                                </a>
                            </li>
                        @empty
                            <li class="text-muted">—</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="mt-10 border-t border-hairline pt-6 text-xs leading-relaxed text-muted">
                <p>
                    &copy; {{ date('Y') }} {{ $footerProfile->name ?? $siteName }}.
                    Personal website of {{ $footerProfile->name ?? 'the author' }}. Not an official publication of The Daily Star or any other outlet.
                </p>
            </div>
        </div>
    </footer>
</body>
</html>

