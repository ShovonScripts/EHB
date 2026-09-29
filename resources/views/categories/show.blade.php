@php
    // Built here rather than inline in the attribute: a `{$var}` inside a bound
    // Blade attribute is emitted unquoted and produces an invalid compiled view.
    $metaDescription = $section->description
        ?: Str::limit($section->name.' — '.$total.' '.Str::plural('piece', $total).' filed with The Daily Star.', 155);

    $intro = $section->description
        ?: $total.' '.Str::plural('piece', $total).' filed under '.$section->name.' at The Daily Star.';
@endphp

<x-app-layout
    :title="$section->name"
    :description="$metaDescription"
    :noindex="$activeTopic !== null"
    :breadcrumbs="[
        ['label' => 'Reporting', 'url' => route('work.index')],
        ['label' => 'Sections', 'url' => route('sections.index')],
        ['label' => $section->name],
    ]"
>
    <div class="container-wide py-14">
        <p class="eyebrow">Section</p>
        <h1 class="mt-3 text-4xl font-bold text-ink">{{ $section->name }}</h1>

        <p class="mt-4 max-w-2xl leading-relaxed text-muted">{{ $intro }}</p>

        <dl class="mt-6 flex flex-wrap gap-x-8 gap-y-2 text-sm text-muted">
            <div class="flex gap-2">
                <dt class="text-divider">Pieces</dt>
                <dd class="font-semibold text-ink">{{ $total }}</dd>
            </div>
            @if($firstAt)
                <div class="flex gap-2">
                    <dt class="text-divider">Filed</dt>
                    <dd class="font-semibold text-ink">
                        {{ $firstAt->format('M Y') }} &ndash; {{ $lastAt->format('M Y') }}
                    </dd>
                </div>
            @endif
        </dl>

        @if($topics->isNotEmpty())
            <section class="mt-8 border-t border-hairline pt-6" aria-labelledby="beat-heading">
                <h2 id="beat-heading" class="eyebrow">Beats in this section</h2>
                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="{{ route('sections.show', $section->slug) }}"
                       class="filter-chip {{ $activeTopic === null ? 'filter-chip-active' : '' }}">All</a>
                    @foreach($topics as $topic)
                        <a href="{{ route('sections.show', ['slug' => $section->slug, 'topic' => $topic->slug]) }}"
                           @class(['filter-chip', 'filter-chip-active' => $activeTopic === $topic->slug])>
                            {{ $topic->name }}
                            <span class="ml-1 opacity-60">{{ $topic->content_items_count }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <p class="sr-only" aria-live="polite">{{ $items->total() }} results</p>

        @if($items->isEmpty())
            <div class="mt-12 border border-dashed border-hairline bg-surface py-16 text-center">
                <p class="text-muted">Nothing filed under this section yet.</p>
                <a href="{{ route('work.index') }}" class="link-accent mt-4 inline-block">Back to all reporting</a>
            </div>
        @else
            <div class="mt-8 divide-y divide-hairline border border-hairline bg-surface">
                @foreach($items as $item)
                    <x-content-row :item="$item" :show-category="false" />
                @endforeach
            </div>

            <div class="mt-10">
                {{ $items->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
