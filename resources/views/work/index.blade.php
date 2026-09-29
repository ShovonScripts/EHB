<x-app-layout
    title="Reporting"
    description="Every piece published in The Daily Star, newest first — browsable by section, by beat and by year."
    :breadcrumbs="[['label' => 'Reporting']]"
    :noindex="$hasFilters"
>
    <div class="container-wide py-14">
        <p class="eyebrow">Published in The Daily Star</p>
        <h1 class="mt-3 text-4xl font-bold text-ink">Reporting</h1>
        <p class="mt-4 max-w-2xl text-muted">
            {{ $total }} {{ Str::plural('piece', $total) }} filed with The Daily Star
            @if($firstAt && $lastAt)
                between {{ $firstAt->format('F Y') }} and {{ $lastAt->format('F Y') }}
            @endif
            — each entry is a headline and summary here, with the full piece at its original publication.
        </p>

        <div class="mt-8 flex flex-wrap items-center gap-4 border-y border-hairline py-4 text-sm">
            <a href="{{ route('sections.index') }}" class="link-accent font-semibold">
                Browse by section
                <span class="font-normal text-muted">({{ count($sectionChips) - 1 }})</span>
            </a>
            <span class="text-hairline-dark" aria-hidden="true">·</span>
            <a href="{{ route('topics.index') }}" class="link-accent font-semibold">
                Browse by beat
                <span class="font-normal text-muted">({{ count($topicChips) - 1 }})</span>
            </a>
            <span class="text-hairline-dark" aria-hidden="true">·</span>
            <a href="{{ route('archive') }}" class="link-accent">Full archive</a>
        </div>

        @foreach([
            'Section' => $sectionChips,
            'Beat' => $topicChips,
            'Year' => $yearChips,
        ] as $axisLabel => $chips)
            @if(count($chips) > 1)
                <section class="mt-8" aria-labelledby="filter-{{ \Illuminate\Support\Str::slug($axisLabel) }}">
                    <h2 id="filter-{{ \Illuminate\Support\Str::slug($axisLabel) }}" class="eyebrow">{{ $axisLabel }}</h2>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach($chips as $chip)
                            <a href="{{ $chip['url'] }}"
                               @class(['filter-chip', 'filter-chip-active' => $chip['active']])>
                                {{ $chip['label'] }}
                                @if($chip['count'] !== null)
                                    <span class="ml-1 opacity-60">{{ $chip['count'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach

        <p class="sr-only" aria-live="polite">{{ $items->total() }} results</p>

        @if($items->isEmpty())
            <div class="mt-12 border border-dashed border-hairline bg-surface py-16 text-center">
                <p class="text-muted">No pieces match this combination of filters.</p>
                <a href="{{ route('work.index') }}" class="link-accent mt-4 inline-block">Clear filters</a>
            </div>
        @else
            <div class="mt-10 divide-y divide-hairline border border-hairline bg-surface">
                @foreach($items as $item)
                    <x-content-row :item="$item" />
                @endforeach
            </div>

            <div class="mt-10">
                {{ $items->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
