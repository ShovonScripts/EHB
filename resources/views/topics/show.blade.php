@php
    $crumbs = [
        ['label' => 'Topics'],
        ['label' => $topic->name],
    ];
@endphp

<x-app-layout
    :title="$topic->name"
    :description="$topic->description ?: 'A curated dossier on '.$topic->name.' — related reporting across types and sources.'"
    :canonical="url(route('topics.show', $topic->slug))"
    :breadcrumbs="$crumbs"
>
    <div class="container-wide py-14">
        <p class="eyebrow">Topic Dossier</p>
        <h1 class="mt-3 text-4xl font-bold text-ink">{{ $topic->name }}</h1>

        @if($topic->featuredImage)
            <img src="{{ $topic->featuredImage->url }}"
                 alt="{{ $topic->featuredImage->alt_text ?? $topic->name }}"
                 class="mt-6 aspect-[16/9] w-full max-w-3xl border border-hairline object-cover"
                 width="1200" height="675"
                 loading="lazy">
        @endif

        @if($topic->description)
            <p class="mt-5 max-w-2xl text-lg leading-relaxed text-muted">{{ $topic->description }}</p>
        @endif

        @if($items->isEmpty())
            <div class="mt-12 border border-dashed border-hairline bg-surface py-16 text-center">
                <p class="text-muted">No published pieces filed under this topic yet.</p>
                <a href="{{ route('archive') }}" class="link-accent mt-4 inline-block">Browse the full archive →</a>
            </div>
        @else
            {{-- Keeps the outline sequential: h1 (dossier) > h2 (listing) > h3
                 (each card title), with no visible copy added. --}}
            <h2 class="sr-only">Pieces in this dossier</h2>
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
