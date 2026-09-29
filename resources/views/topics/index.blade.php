<x-app-layout
    title="Beats"
    description="The editorial beats Emrul Hasan Bappi covers, from courtrooms and prisons to policing, narcotics and public safety."
    :breadcrumbs="[['label' => 'Beats']]"
>
    <div class="container-wide py-14">
        <p class="eyebrow">Editorial dossiers</p>
        <h1 class="mt-3 text-4xl font-bold text-ink">Beats</h1>
        <p class="mt-4 max-w-2xl leading-relaxed text-muted">
            A section is where a piece was filed; a beat is what it is about. These dossiers cut across
            sections — the narcotics reporting sits under both Crime &amp; Justice and Bangladesh, and the
            courtroom stories run across both. {{ $total }} placements across
            {{ $topics->count() }} {{ Str::plural('dossier', $topics->count()) }}.
        </p>

        @if($topics->isEmpty())
            <div class="mt-12 border border-dashed border-hairline bg-surface py-16 text-center">
                <p class="text-muted">No beats have been filed yet.</p>
                <a href="{{ route('work.index') }}" class="link-accent mt-4 inline-block">Browse all reporting</a>
            </div>
        @else
            {{-- Widest dossier first, so the grid reads as a ranked list. --}}
            @php($bySize = $topics->sortByDesc('content_items_count')->values())
            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($bySize as $topic)
                    <a href="{{ route('topics.show', $topic->slug) }}"
                       class="group flex flex-col border border-hairline bg-surface p-6 transition-shadow hover:shadow-[0_2px_16px_rgba(26,26,26,0.07)]">
                        <h2 class="font-serif text-xl font-bold text-ink transition-colors group-hover:text-accent">
                            {{ $topic->name }}
                        </h2>

                        <p class="mt-2 text-sm leading-relaxed text-muted">
                            {{ filled($topic->description) ? \Illuminate\Support\Str::limit($topic->description, 140) : 'No description yet.' }}
                        </p>

                        <div class="mt-5 flex items-center gap-3 text-sm">
                            <span class="font-semibold text-ink">
                                {{ $topic->content_items_count }} {{ Str::plural('piece', $topic->content_items_count) }}
                            </span>
                            @if($topic->last_at)
                                <span class="text-xs text-muted">
                                    latest {{ $topic->last_at->format('M Y') }}
                                </span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
