@php
    $crumbs = [
        ['label' => 'Publications', 'url' => route('publications.index')],
        ['label' => $publication->name],
    ];
@endphp

<x-app-layout
    :title="$publication->name"
    :description="$publication->description ?: 'All archived work published in '.$publication->name.'.'"
    :canonical="url(route('publications.show', $publication->slug))"
    :breadcrumbs="$crumbs"
>
    <div class="container-wide py-14">
        <div class="flex flex-wrap items-center gap-6">
            @if($publication->logo)
                <img src="{{ $publication->logo->url }}" alt="{{ $publication->name }} logo" class="h-14 w-auto object-contain" width="160" height="56">
            @endif
            <div>
                <p class="eyebrow">Publication</p>
                <h1 class="mt-2 text-4xl font-bold text-ink">{{ $publication->name }}</h1>
            </div>
        </div>

        @if($publication->description)
            <p class="mt-5 max-w-2xl leading-relaxed text-muted">{{ $publication->description }}</p>
        @endif

        @if($publication->website_url)
            <a href="{{ $publication->website_url }}" rel="noopener noreferrer" target="_blank" class="link-accent mt-3 inline-block">
                Visit {{ $publication->name }} →
            </a>
        @endif

        @if($items->isEmpty())
            <div class="mt-12 border border-dashed border-hairline bg-surface py-16 text-center">
                <p class="text-muted">No archived pieces from this outlet yet.</p>
            </div>
        @else
            {{-- The card grid titles are h3, so it needs an h2 between them and
                 the page h1 or the outline skips a level. No visible copy is
                 added for it — the h1 above already names the outlet. --}}
            <h2 class="sr-only">All published pieces</h2>
            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
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
