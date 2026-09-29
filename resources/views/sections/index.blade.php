<x-app-layout
    :title="$definition['label']"
    :description="'Browse all '.$definition['label'].' — newest first.'"
    :breadcrumbs="[['label' => $definition['label']]]"
>
    <div class="container-wide py-14">
        <p class="eyebrow">Section</p>
        <h1 class="mt-3 text-4xl font-bold text-ink">{{ $definition['label'] }}</h1>

        {{-- Filter bar — category (FR-111 style filters, simplified per section) --}}
        @if($categories->isNotEmpty())
            <div class="mt-6 flex flex-wrap items-center gap-2 border-b border-hairline pb-5">
                <a href="{{ route('section.'.$section) }}"
                   class="filter-chip {{ $activeCategory === null ? 'filter-chip-active' : '' }}">
                    All
                </a>
                @foreach($categories as $cat)
                    <a href="{{ route('section.'.$section, ['category' => $cat->slug]) }}"
                       class="filter-chip {{ $activeCategory === $cat->slug ? 'filter-chip-active' : '' }}">
                        {{ $cat->name }}
                    </a>
                @endforeach
            </div>
        @endif

        @if($items->isEmpty())
            <div class="mt-12 border border-dashed border-hairline bg-surface py-16 text-center">
                <p class="text-muted">
                    @if($activeCategory)
                        No published pieces in this category yet.
                    @else
                        No published pieces in this section yet — check back soon.
                    @endif
                </p>
                <a href="{{ route('archive') }}" class="mt-4 inline-block link-accent">Browse the full archive →</a>
            </div>
        @else
                {{-- Card titles are h3; without this the outline jumps h1 > h3.
                     The h1 above already names the section, so nothing visible
                     is added. --}}
                <h2 class="sr-only">All {{ $definition['label'] }}</h2>
                <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($items as $item)
                    <x-content-card :item="$item" />
                @endforeach
            </div>

            <div class="mt-10">
                {{ $items->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
