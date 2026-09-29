@php
    // SEO.md §6 — Article schema for internal pieces.
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'NewsArticle',
        'headline' => $item->title,
        'description' => $item->summary,
        'datePublished' => $item->published_at?->toIso8601String(),
        'url' => url($item->publicPath()),
        'author' => [
            '@type' => 'Person',
            'name' => $item->author?->name ?? config('app.name'),
            'url' => route('about'),
        ],
        'publisher' => [
            '@type' => 'Person',
            'name' => $item->author?->name ?? config('app.name'),
        ],
    ];
    if ($item->featuredImage) {
        // Absolute, because a consumer of structured data is not a browser
        // sitting on this page: it has no base URL to resolve `/storage/…`
        // against, and an unresolvable `image` means the piece cannot be
        // surfaced as a rich result. The rendered <img> elsewhere on this page
        // still uses the root-relative `url`; only the metadata is absolutised.
        $jsonLd['image'] = [$item->ogImage?->absolute_url ?: $item->featuredImage->absolute_url];
    }

    $crumbs = [
        ['label' => $definition['label'], 'url' => route('section.'.$section)],
        ['label' => $item->title],
    ];
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
        {{-- Article header --}}
        <header class="animate-entrance border-b border-hairline">
            <div class="container-reading pt-12 pb-10">
                <div class="flex flex-wrap items-center gap-3 text-xs">
                    <span class="eyebrow">{{ \App\Support\ContentTypes::typeLabel($item->content_type) }}</span>
                    @if($item->category)
                        <span class="text-divider">·</span>
                        <a href="{{ route('section.'.$section, ['category' => $item->category->slug]) }}" class="text-muted hover:text-accent">{{ $item->category->name }}</a>
                    @endif
                    @if($item->publication)
                        <span class="text-divider">·</span>
                        <a href="{{ route('publications.show', $item->publication->slug) }}" class="text-muted hover:text-accent">{{ $item->publication->name }}</a>
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
                    @if($item->reading_time_minutes)
                        <span>{{ $item->reading_time_minutes }} min read</span>
                    @endif
                </div>

                {{-- Type-specific meta --}}
                @if($item->content_type === 'interview' && $item->interviewee_name)
                    <p class="mt-4 border-l-2 border-accent pl-4 text-sm text-muted">
                        Interview with <span class="font-semibold text-ink">{{ $item->interviewee_name }}</span>@if($item->interviewee_title), {{ $item->interviewee_title }}@endif
                    </p>
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

        {{-- Video embed for content_type=video (SECURITY.md §6 — only
             http(s) URLs are ever rendered in a frame) --}}
        @if($item->content_type === 'video' && $item->safe_video_url)
            <div class="container-reading mt-8">
                <div class="aspect-video border border-hairline bg-ink">
                    <iframe src="{{ $item->safe_video_url }}"
                            class="h-full w-full"
                            title="{{ $item->title }}"
                            frameborder="0"
                            allowfullscreen
                            loading="lazy"
                            referrerpolicy="strict-origin-when-cross-origin"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
                </div>
            </div>
        @endif

        <div class="container-reading mt-10">
            {{-- The journalist's own text for this piece, under the image.
                 Rendered on external pages too (see work/show), so it is kept
                 here too rather than appearing on one page type only. --}}
            @if(filled($item->news_body))
                <x-rich-text :html="$item->news_body" />
            @endif

            {{-- Internal: full body (FR-201) --}}
            @if($item->body)
                <div class="article-body text-ink">
                    {!! $item->body !!}
                </div>
            @else
                <p class="text-muted">This piece has no on-site body — see the summary above.</p>
            @endif

            {{-- Photo-story gallery --}}
        @if($item->content_type === 'photo_story' && $item->gallery->isNotEmpty())
            <div class="mt-12">
                <p class="eyebrow">Gallery</p>
                <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($item->gallery as $media)
                        <figure>
                            <img src="{{ $media->url }}"
                                 alt="{{ $media->alt_text ?? $item->title }}"
                                 class="aspect-[4/3] w-full border border-hairline object-cover"
                                 loading="lazy">
                            @if($media->pivot->caption ?? null)
                                <figcaption class="mt-2 text-center text-xs text-muted">{{ $media->pivot->caption }}</figcaption>
                            @endif
                        </figure>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Tags & topics --}}
            @if($item->tags->isNotEmpty() || $item->topics->isNotEmpty())
                <div class="mt-10 flex flex-wrap items-center gap-2 border-t border-hairline pt-6">
                    @foreach($item->topics as $topic)
                        <a href="{{ route('topics.show', $topic->slug) }}" class="filter-chip">{{ $topic->name }}</a>
                    @endforeach
                    @foreach($item->tags as $tag)
                        <a href="{{ route('archive', ['tag' => $tag->slug]) }}" class="filter-chip">{{ $tag->name }}</a>
                    @endforeach
                </div>
            @endif
        </div>

        <x-share-links :url="url($item->publicPath())" :title="$item->title" />
    </article>

    {{-- Related content — FR-209 --}}
    @if($related->isNotEmpty())
        <section class="reveal mt-16 border-t border-hairline bg-surface">
            <div class="container-wide py-12">
                <h2 class="eyebrow">Related Reporting</h2>
                <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($related as $rel)
                        <x-content-card :item="$rel" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-app-layout>
