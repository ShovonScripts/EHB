@props(['item', 'showCategory' => true])

{{-- DESIGN_SYSTEM.md §7 / FRONTEND.md §2 — DenseContentList row.
     One row per piece: date, section, headline + dek, outbound source.
     Used by /work, /archive and the section landing pages, so the three
     listings of the same collection read identically. --}}
@php
    $date = $item->published_at ?? $item->created_at;
    $isExternal = $item->isExternal();
@endphp

<article {{ $attributes->class(['flex flex-wrap items-baseline gap-x-4 gap-y-1 px-5 py-4 transition-colors hover:bg-paper']) }}>
    <time datetime="{{ $date->toDateString() }}" class="w-24 shrink-0 text-xs text-muted">
        {{ $date->format('M j, Y') }}
    </time>

    @if($showCategory && $item->category)
        <a href="{{ route('sections.show', $item->category->slug) }}"
           class="eyebrow w-32 shrink-0 transition-colors hover:text-accent">{{ $item->category->name }}</a>
    @else
        <span class="eyebrow w-32 shrink-0">{{ \App\Support\ContentTypes::typeLabel($item->content_type) }}</span>
    @endif

    <div class="min-w-0 flex-1">
        <a href="{{ $item->publicPath() }}" class="link-accent font-serif font-bold text-ink">
            {{ $item->title }}
        </a>
        @if($item->summary)
            <p class="mt-0.5 text-sm text-muted">{{ \Illuminate\Support\Str::limit($item->summary, 200) }}</p>
        @endif
    </div>

    @if($isExternal)
        <span class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-accent">
            {{ $item->publication?->name ?? 'External' }}
            <svg class="arrow-nudge h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
        </span>
    @else
        <span class="shrink-0 text-xs text-muted">
            {{ $item->reading_time_minutes ? $item->reading_time_minutes.' min read' : 'On this site' }}
        </span>
    @endif
</article>
