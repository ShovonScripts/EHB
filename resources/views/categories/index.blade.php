<x-app-layout
    title="Sections"
    description="Every section Emrul Hasan Bappi files under at The Daily Star, with piece counts."
    :breadcrumbs="[['label' => 'Sections']]"
>
    <div class="container-wide py-14">
        <p class="eyebrow">How the work is filed</p>
        <h1 class="mt-3 text-4xl font-bold text-ink">Sections</h1>
        <p class="mt-4 max-w-2xl leading-relaxed text-muted">
            The outlet files bylines under a small number of standing sections. {{ $total }}
            {{ Str::plural('piece', $total) }} sit across the {{ $sections->count() }} below — these are the
            sections as The Daily Star names them, so a reader who knows the paper can find the same beat here.
        </p>

        <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($sections as $section)
                <a href="{{ route('sections.show', $section->slug) }}"
                   class="group flex flex-col border border-hairline bg-surface p-6 transition-shadow hover:shadow-[0_2px_16px_rgba(26,26,26,0.07)]">
                    <h2 class="font-serif text-xl font-bold text-ink transition-colors group-hover:text-accent">
                        {{ $section->name }}
                    </h2>

                    <p class="mt-2 text-sm leading-relaxed text-muted">
                        {{ filled($section->description) ? \Illuminate\Support\Str::limit($section->description, 120) : 'No description yet.' }}
                    </p>

                    <div class="mt-5 flex items-center gap-3 text-sm">
                        <span class="font-semibold text-ink">
                            {{ $section->content_items_count }} {{ Str::plural('piece', $section->content_items_count) }}
                        </span>
                        @if($section->last_at)
                            <span class="text-xs text-muted">
                                latest {{ $section->last_at->format('M Y') }}
                            </span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>

        <p class="mt-10 text-sm text-muted">
            Looking for a beat rather than a section? Browse the
            <a href="{{ route('topics.index') }}" class="link-accent">beat dossiers</a>,
            or the <a href="{{ route('archive') }}" class="link-accent">full archive</a>.
        </p>
    </div>
</x-app-layout>
