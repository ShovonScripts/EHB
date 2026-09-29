@php
    $name = $profile?->name ?? config('app.name');
    $title = $profile?->title ?? 'Journalist';
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Person',
        'name' => $name,
        'jobTitle' => $title,
        'url' => url('/'),
    ];
    if ($profile?->social_links) {
        $sameAs = array_values(array_filter([
            $profile->social_links['twitter'] ?? null,
            $profile->social_links['linkedin'] ?? null,
            $profile->social_links['facebook'] ?? null,
        ]));
        if ($sameAs) {
            $jsonLd['sameAs'] = $sameAs;
        }
    }
@endphp

<x-app-layout
    :title="$name.' — '.$title"
    :description="\Illuminate\Support\Str::limit($profile?->short_bio ?? 'Independent journalist. Reporting, investigations, interviews, and analysis — all in one archive.', 155)"
    :json-ld="$jsonLd"
>
    {{-- 1. Hero — FRONTEND.md §4.1/4.2
         The two-column grid is only applied when there is a portrait to put in
         it. With no photo the `1fr auto` track still reserved an empty second
         column, so the text was squeezed left and the hero read as broken
         rather than deliberate. --}}
    <section class="animate-entrance border-b border-hairline">
        <div @class([
            'container-wide grid items-center gap-10 py-16 md:py-24',
            'md:grid-cols-[1fr_auto]' => $profile?->photo,
        ])>
            <div class="max-w-2xl">
                <p class="eyebrow">Journalist &amp; Reporter</p>
                <h1 class="mt-4 text-4xl leading-tight font-bold text-ink md:text-5xl">{{ $name }}</h1>
                <p class="mt-4 text-lg text-muted md:text-xl">{{ $title }}</p>
                @if($profile?->short_bio)
                    <p class="mt-5 leading-relaxed text-muted">{{ $profile->short_bio }}</p>
                @endif
                <div class="mt-8 flex flex-wrap gap-4">
                    <a href="{{ route('work.index') }}" class="btn-primary">Browse the Reporting</a>
                    <a href="{{ route('contact.create') }}" class="btn-outline">Get in Touch</a>
                </div>
            </div>
            @if($profile?->photo)
                <div class="justify-self-center md:justify-self-end">
                    <img src="{{ $profile->photo->url }}"
                         alt="Portrait of {{ $name }}"
                         class="h-56 w-56 border border-hairline object-cover md:h-72 md:w-72"
                         width="288" height="288">
                </div>
            @endif
        </div>
    </section>

    {{-- 2. The archive at a glance — what there is and the three ways in
         Every band label on this page is a real <h2>, not a styled <p>. The
         eyebrow class is purely visual and Tailwind's preflight zeroes heading
         margins, so the two render identically — but only the heading version
         is reachable when navigating by heading, which is how a screen-reader
         user skips a band. Sub-headings inside a band are h3 so the outline
         stays sequential (WCAG 1.3.1 / 2.4.6). --}}
    @if($total > 0)
        <section class="border-b border-hairline bg-surface">
            <div class="container-wide py-14">
                <div class="flex flex-wrap items-baseline justify-between gap-4">
                    <h2 class="eyebrow">The Archive</h2>
                    <a href="{{ route('archive') }}" class="link-accent text-sm">Every piece, newest first →</a>
                </div>

                <dl class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <dt class="text-sm text-muted">Pieces published</dt>
                        <dd class="mt-1 font-serif text-3xl font-bold text-ink">{{ $total }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted">Filing since</dt>
                        <dd class="mt-1 font-serif text-3xl font-bold text-ink">
                            {{ $firstAt?->format('M Y') ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted">Outlet sections</dt>
                        <dd class="mt-1 font-serif text-3xl font-bold text-ink">{{ $sections->count() }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted">Beats covered</dt>
                        <dd class="mt-1 font-serif text-3xl font-bold text-ink">{{ $topics->count() }}</dd>
                    </div>
                </dl>

                <div class="mt-10 grid gap-8 md:grid-cols-2">
                    <div>
                        <h3 class="font-serif text-lg font-bold text-ink">
                            <a href="{{ route('sections.index') }}" class="transition-colors hover:text-accent">By section →</a>
                        </h3>
                        <p class="mt-1 text-sm text-muted">The sections the outlet files bylines under.</p>
                        <ul class="mt-4 space-y-2 text-sm">
                            @foreach($sections->take(6) as $section)
                                <li class="flex items-baseline justify-between gap-4 border-b border-hairline/60 pb-2">
                                    <a href="{{ route('sections.show', $section->slug) }}" class="link-accent">{{ $section->name }}</a>
                                    <span class="shrink-0 text-xs text-muted">{{ $section->content_items_count }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div>
                        <h3 class="font-serif text-lg font-bold text-ink">
                            <a href="{{ route('topics.index') }}" class="transition-colors hover:text-accent">By beat →</a>
                        </h3>
                        <p class="mt-1 text-sm text-muted">Dossiers that cut across sections.</p>
                        <ul class="mt-4 space-y-2 text-sm">
                            @foreach($topics->take(6) as $topic)
                                <li class="flex items-baseline justify-between gap-4 border-b border-hairline/60 pb-2">
                                    <a href="{{ route('topics.show', $topic->slug) }}" class="link-accent">{{ $topic->name }}</a>
                                    <span class="shrink-0 text-xs text-muted">{{ $topic->content_items_count }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- 3. Featured work — FRONTEND.md §4.3 --}}
    @if($featured->isNotEmpty())
        <section class="reveal container-wide py-14">
            <div class="flex items-baseline justify-between">
                <h2 class="eyebrow">Featured Work</h2>
                <a href="{{ route('archive') }}" class="link-accent text-sm">View all →</a>
            </div>
            <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($featured as $item)
                    <div class="animate-entrance stagger-{{ $loop->iteration }}">
                        <x-content-card :item="$item" />
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- 4. Latest reporting — FRONTEND.md §4.4
         A dense list, not a card grid. None of the outlet pieces carry a
         featured image (the only image URLs in the source export are
         300x170 outlet thumbnails, not ours to redistribute), so a 3-column
         card grid rendered six text-only boxes with a hole where the picture
         should be. This is the same row used by /work, /archive and the
         section pages — one reading experience for one collection.
         Featured Work above stays on cards: that is the showcase slot, and it
         is where images belong when he adds them. --}}
    @if($latest->isNotEmpty())
        {{-- The band sits on paper and the list carries the single surface box.
             Both previously wore bg-surface with a border, which put a second
             frame just inside the first and flattened the contrast between
             them. One frame, one surface. --}}
        <section class="reveal border-y border-hairline">
            <div class="container-wide py-14">
                <div class="flex items-baseline justify-between gap-4">
                    <h2 class="eyebrow">Latest Reporting</h2>
                    <a href="{{ route('work.index') }}" class="link-accent text-sm">All {{ $total }} pieces →</a>
                </div>
                <div class="mt-6 divide-y divide-hairline border border-hairline bg-surface">
                    @foreach($latest as $item)
                        <x-content-row :item="$item" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- 5. As Seen In — FRONTEND.md §4.5
         Only worth a full-width band once there is more than one outlet. With a
         single publication this rendered one centred word under a heading, and
         the outlet's name is already stated in the hero's job title and again
         in the footer column — a whole section of vertical space to repeat it. --}}
    @if($publications->count() > 1)
        <section class="reveal container-wide py-14">
            <h2 class="eyebrow text-center">As Seen In</h2>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-x-10 gap-y-6">
                @foreach($publications as $pub)
                    <a href="{{ route('publications.show', $pub->slug) }}"
                       class="group flex items-center gap-3 text-muted transition-colors hover:text-accent"
                       title="{{ $pub->name }}{{ $pub->content_items_count ? ' — '.$pub->content_items_count.' pieces' : '' }}">
                        @if($pub->logo)
                            <img src="{{ $pub->logo->url }}" alt="{{ $pub->name }} logo" class="h-8 w-auto grayscale transition-all group-hover:grayscale-0" loading="lazy">
                        @else
                            <span class="font-serif text-lg font-bold">{{ $pub->name }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- 6. Investigations spotlight — FRONTEND.md §4.6 --}}
    @if($investigations->isNotEmpty())
        <section class="reveal border-y border-hairline bg-surface">
            <div class="container-wide py-14">
                <div class="flex items-baseline justify-between">
                    <h2 class="eyebrow">Investigations</h2>
                    <a href="{{ route('section.investigations') }}" class="link-accent text-sm">All investigations →</a>
                </div>
                <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($investigations as $item)
                        <div class="animate-entrance stagger-{{ $loop->iteration }}">
                            <x-content-card :item="$item" />
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- 7. About teaser — FRONTEND.md §4.7 --}}
    @if($profile?->long_bio)
        <section class="reveal container-reading py-14">
            <h2 class="eyebrow">About</h2>
            <p class="mt-4 font-serif text-xl leading-relaxed text-ink md:text-2xl">
                {{ \Illuminate\Support\Str::limit(strip_tags($profile->long_bio), 340) }}
            </p>
            <a href="{{ route('about') }}" class="link-accent mt-5 inline-block">Read the full biography →</a>
        </section>
    @endif

    {{-- 8. Contact CTA — FRONTEND.md §4.8 --}}
    <section class="reveal border-t border-hairline bg-surface">
        <div class="container-reading py-16 text-center">
            <h2 class="eyebrow">Contact</h2>
            <p class="mt-3 text-3xl font-bold text-ink">Have a tip, question, or collaboration idea?</p>
            <p class="mx-auto mt-4 max-w-xl text-muted">Reach out directly — messages land in a private inbox, reviewed personally.</p>
            <a href="{{ route('contact.create') }}" class="btn-primary mt-8">Send a Message</a>
        </div>
    </section>
</x-app-layout>
