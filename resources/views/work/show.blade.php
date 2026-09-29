@php
    // SEO.md §6 — CreativeWork citation pattern for external work
    // (never Article schema claiming original authorship).
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'CreativeWork',
        'name' => $item->title,
        'description' => $item->summary,
        'url' => url($item->publicPath()),
        'datePublished' => $item->published_at?->toIso8601String(),
        'author' => [
            '@type' => 'Person',
            'name' => $item->author?->name ?? config('app.name'),
        ],
    ];
    if ($item->external_url) {
        $jsonLd['isBasedOn'] = $item->external_url;
        $jsonLd['citation'] = $item->external_url;
    }
    if ($item->publication) {
        $jsonLd['publisher'] = [
            '@type' => 'Organization',
            'name' => $item->publication->name,
        ];
    }

    // "Reporting", not "External Work" — the nav, the footer and /work itself
    // all use the current name, and a breadcrumb that disagrees with the nav
    // it sits under reads as a bug.
    $crumbs = [
        ['label' => 'Reporting', 'url' => route('work.index')],
        // The headline is the unpredictable value here and it is repeated in
        // full in the h1 directly below, so it is bounded here rather than
        // allowed to wrap the breadcrumb onto three lines on a phone.
        ['label' => \Illuminate\Support\Str::limit($item->title, 60)],
    ];

    $outletName = $item->publication?->name ?? 'the original outlet';
@endphp

<x-app-layout
    :title="$item->seo_title ?: $item->title"
    :description="$item->seo_description ?: \Illuminate\Support\Str::limit($item->summary, 155)"
    :canonical="$item->canonical_url_override ?: url($item->publicPath())"
    :og-image="$item->ogImage?->url ?: $item->featuredImage?->url"
    :og-image-alt="$item->ogImage?->alt_text ?: $item->featuredImage?->alt_text"
    og-type="article"
    :json-ld="$jsonLd"
    :breadcrumbs="$crumbs"
>
    <article>
        <header class="border-b border-hairline">
            <div class="container-reading pt-12 pb-10">
                <div class="flex flex-wrap items-center gap-3 text-xs">
                    <span class="eyebrow">{{ \App\Support\ContentTypes::typeLabel($item->content_type) }}</span>
                    @if($item->category)
                        <span class="text-divider" aria-hidden="true">·</span>
                        <a href="{{ route('sections.show', $item->category->slug) }}"
                           class="text-muted transition-colors hover:text-accent">{{ $item->category->name }}</a>
                    @endif
                    @if($item->publication)
                        <span class="text-divider" aria-hidden="true">·</span>
                        <a href="{{ route('publications.show', $item->publication->slug) }}"
                           class="text-muted transition-colors hover:text-accent">{{ $item->publication->name }}</a>
                    @endif
                </div>

                <h1 class="mt-4 text-3xl leading-tight font-bold text-ink md:text-4xl">{{ $item->title }}</h1>

                @if($item->summary)
                    <p class="mt-4 font-serif text-lg leading-relaxed text-muted italic md:text-xl">{{ $item->summary }}</p>
                @endif

                <div class="mt-6 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted">
                    @if($item->author)
                        <span>By <a href="{{ route('about') }}" class="text-ink hover:text-accent">{{ $item->author->name }}</a></span>
                    @endif
                    <time datetime="{{ ($item->published_at ?? $item->created_at)->toIso8601String() }}">
                        {{ ($item->published_at ?? $item->created_at)->format('F j, Y') }}
                    </time>
                </div>

                {{-- Filed under. Previously the beats and tags sat below the
                     callout as bare pills with nothing naming them, so a lone
                     "Policing & Investigation" chip read as decoration rather
                     than a link, and the outlet section was not shown at all —
                     the one piece of orientation this page needs. --}}
                @if($item->topics->isNotEmpty() || $item->tags->isNotEmpty())
                    <div class="mt-5 flex flex-wrap items-center gap-2">
                        <span class="text-xs tracking-[0.18em] text-muted uppercase">Filed under</span>
                        @foreach($item->topics as $topic)
                            <a href="{{ route('topics.show', $topic->slug) }}" class="filter-chip">{{ $topic->name }}</a>
                        @endforeach
                        @foreach($item->tags as $tag)
                            <a href="{{ route('archive', ['tag' => $tag->slug]) }}" class="filter-chip">{{ $tag->name }}</a>
                        @endforeach
                    </div>
                @endif
            </div>
        </header>

        {{-- Featured image --}}
        @if($item->featuredImage)
            <div class="container-reading mt-8">
                <figure>
                    <img src="{{ $item->featuredImage->url }}"
                         alt="{{ $item->featuredImage->alt_text ?? $item->title }}"
                         class="aspect-[16/9] w-full border border-hairline object-cover"
                         width="1200" height="675">
                    @if($item->featuredImage->caption)
                        <figcaption class="mt-2 text-center text-xs text-muted">{{ $item->featuredImage->caption }}</figcaption>
                    @endif
                </figure>
            </div>
        @endif

        {{-- The journalist's own text for this piece, under the image.

             This is the `news_body` field from the admin, and it is offered for
             every piece regardless of source type — which is the point: an
             external piece can carry the journalist's own reporting (context,
                     analysis, a fuller account) without the page becoming a
                     reproduction of the outlet's article.

             Falls back to `body` so a piece written before `news_body` existed
                     still renders something. --}}
        @if(filled($item->news_body) || filled($item->body))
            <div class="container-reading mt-10">
                <x-rich-text :html="$item->news_body ?: $item->body" />
            </div>
        @endif

        <div class="container-reading mt-10">
            {{-- The callout — FR-114/FR-115. This is the whole point of the page,
                 so it is the focal point rather than a bordered aside. The raw
                 URL used to be printed underneath the button, wrapped mid-word
                 across three lines; the button already goes there, so the
                 provenance line carries the outlet and the date instead. --}}
            @if($item->external_url)
                <div class="border border-accent/30 bg-surface p-6 text-center sm:p-8">
                    <p class="eyebrow">The full report</p>

                    <h2 class="mt-3 font-serif text-xl leading-snug font-bold text-ink md:text-2xl">
                        Read the original at {{ $outletName }}
                    </h2>

                    <p class="mx-auto mt-3 max-w-md text-sm leading-relaxed text-muted">
                        @if(filled($item->news_body) || filled($item->body))
                            This site is the journalist&rsquo;s own archive. The full report is
                            published by {{ $outletName }} and linked here.
                        @else
                            This site is the journalist&rsquo;s own archive. The article is summarised and
                            linked here rather than reproduced.
                        @endif
                    </p>

                    <a href="{{ $item->external_url }}"
                       rel="noopener noreferrer"
                       target="_blank"
                       class="btn-primary mt-6">
                        Read Original Article
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    </a>

                    <p class="mt-4 text-xs text-muted">
                        Filed {{ ($item->published_at ?? $item->created_at)->format('j F Y') }}
                    </p>
                </div>
            @endif

            {{-- Inside the reading column: previously this sat outside it, so
                 the share row sat flush to the page edge while the headline
                 and chips were centred in a 720px column. --}}
            <x-share-links :url="url($item->publicPath())" :title="$item->title" />
        </div>
    </article>

    @if($related->isNotEmpty())
        {{-- Dense rows, not cards. These are all outlet pieces with no
             featured image, so a three-column card grid rendered text-only
             boxes that each repeated the same "News · The Daily Star" badge.
             The row is what /work, /archive and the section pages use. --}}
        <section class="mt-16 border-t border-hairline bg-surface">
            <div class="container-wide py-12">
                <h2 class="eyebrow">Related Work</h2>
                <div class="mt-6 divide-y divide-hairline border border-hairline bg-paper">
                    @foreach($related as $rel)
                        <x-content-row :item="$rel" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-app-layout>
