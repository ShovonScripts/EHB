@props(['item' => null])

{{-- DESIGN_SYSTEM.md §7 — one ContentCard component reused everywhere.
     thumbnail, type badge, title, dek, date, source badge. --}}
@php
    $isExternal = $item->isExternal();
    $typeLabel = \App\Support\ContentTypes::typeLabel($item->content_type);
    $displayDate = $item->published_at?->format('M j, Y') ?? $item->created_at->format('M j, Y');

    // DESIGN_SYSTEM.md §7 — the piece's own image when it has one, and
    // Settings → "Default thumbnail image" when it does not, so a grid of
    // link-outs is not a wall of text. One `?:` chain rather than two ternaries:
    // whatever image this card draws, its alt text comes from that same image
    // and falls back to the title only when neither carries one — an empty alt
    // on a decorative thumbnail fails AccessibilityTest, and a title is better
    // than describing a different picture than the one shown.
    $thumbImage = $item->featuredImage ?? \App\Support\SiteSettings::defaultCardImage();
    $thumbUrl = $thumbImage?->url;
    $thumbAlt = $thumbImage?->alt_text ?: $item->title;
@endphp

<article {{ $attributes->class(['group flex flex-col border border-hairline bg-surface transition-shadow hover:shadow-[0_2px_16px_rgba(26,26,26,0.07)]']) }}>
    @if($thumbUrl)
        <a href="{{ $item->publicPath() }}" class="block aspect-[4/3] overflow-hidden bg-hairline" tabindex="-1" aria-hidden="true">
            <img src="{{ $thumbUrl }}"
                 alt="{{ $thumbAlt }}"
                 class="content-card-thumb h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
                 loading="lazy"
                 width="640"
                 height="480">
        </a>
    @endif

    <div class="flex flex-1 flex-col p-5">
        <div class="flex flex-wrap items-center gap-2 text-xs">
            {{-- Type badge --}}
            <span class="eyebrow">{{ $typeLabel }}</span>

            {{-- Source badge: "On this site" vs "Published in [Publication]" (FR-115) --}}
            @if($isExternal)
                <span class="text-divider">·</span>
                <span class="inline-flex items-center gap-1 text-muted">
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                    {{ $item->publication?->name ?? 'External' }}
                </span>
            @elseif($item->category)
                <span class="text-divider">·</span>
                <span class="text-muted">{{ $item->category->name }}</span>
            @endif
        </div>

        <h3 class="mt-2 font-serif text-[1.12rem] leading-snug font-bold text-ink">
            <a href="{{ $item->publicPath() }}" class="group-hover:text-accent transition-colors">
                {{ $item->title }}
            </a>
        </h3>

        <p class="mt-2 flex-1 text-sm leading-relaxed text-muted">
            {{ \Illuminate\Support\Str::limit($item->summary, 140) }}
        </p>

        <div class="mt-4 flex items-center justify-between text-xs text-muted">
            <time datetime="{{ ($item->published_at ?? $item->created_at)->toDateString() }}">
                {{ $displayDate }}
            </time>

            @if($isExternal)
                <span class="inline-flex items-center gap-1 font-semibold text-accent">
                    Read Original
                    <svg class="arrow-nudge h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                </span>
            @elseif($item->reading_time_minutes)
                <span>{{ $item->reading_time_minutes }} min read</span>
            @endif
        </div>
    </div>
</article>
