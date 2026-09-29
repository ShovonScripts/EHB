<x-app-layout
    title="Publications"
    description="Outlets where this journalist's reporting has appeared."
    :breadcrumbs="[['label' => 'Publications']]"
>
    <div class="container-wide py-14">
        <p class="eyebrow">Outlets</p>
        <h1 class="mt-3 text-4xl font-bold text-ink">Publications</h1>
        <p class="mt-4 max-w-2xl text-muted">
            Reporting, investigations, and commentary published across these outlets. Each publication page collects
            every piece in the archive tied to it.
        </p>

        @if($publications->isEmpty())
            <div class="mt-12 border border-dashed border-hairline bg-surface py-16 text-center">
                <p class="text-muted">No publications listed yet.</p>
            </div>
        @else
            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($publications as $pub)
                    <a href="{{ route('publications.show', $pub->slug) }}"
                       class="group flex flex-col items-center border border-hairline bg-surface p-6 text-center transition-shadow hover:shadow-[0_2px_16px_rgba(26,26,26,0.07)]">
                        @if($pub->logo)
                            <img src="{{ $pub->logo->url }}" alt="{{ $pub->name }} logo" class="h-10 w-auto object-contain grayscale transition-all group-hover:grayscale-0" loading="lazy">
                        @else
                            <span class="font-serif text-lg font-bold text-ink group-hover:text-accent">{{ $pub->name }}</span>
                        @endif
                        <span class="mt-3 font-serif text-sm font-bold text-ink group-hover:text-accent">{{ $pub->name }}</span>
                        <span class="mt-1 text-xs text-muted">
                            {{ $pub->content_items_count }} {{ Str::plural('piece', $pub->content_items_count) }} in archive
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
