<x-app-layout
    title="Search"
    description="Search the full archive — reporting, investigations, interviews, and analysis."
    :noindex="true"
    :breadcrumbs="[['label' => 'Search']]"
>
    <div class="container-reading py-14">
        <p class="eyebrow">Site Search</p>
        <h1 class="mt-3 text-4xl font-bold text-ink">Search</h1>

        <form method="GET" action="{{ route('search') }}" class="mt-6 flex gap-2" role="search">
            <label for="q" class="sr-only">Search query</label>
            <input type="search"
                   id="q"
                   name="q"
                   value="{{ $q }}"
                   placeholder="Search titles, summaries, and body text…"
                   minlength="2"
                   required
                   class="flex-1 border border-hairline bg-surface px-4 py-3 text-ink placeholder:text-muted focus:border-accent"
                   autofocus>
            <button type="submit" class="btn-primary">Search</button>
        </form>

        @if($q === '')
            <p class="mt-8 text-sm text-muted">
                Type at least 2 characters to search across all published work.
            </p>
        @elseif(mb_strlen($q) < 2)
            <p class="mt-8 text-sm text-muted">Please enter at least 2 characters.</p>
        @elseif($items->isEmpty())
            <div class="mt-10 border border-dashed border-hairline bg-surface py-14 text-center">
                <p class="text-muted">No results for “{{ $q }}”.</p>
                <p class="mt-2 text-sm text-muted">Try a different keyword, or browse the <a href="{{ route('archive') }}" class="link-accent">full archive</a>.</p>
            </div>
        @else
            <h2 class="sr-only">{{ $items->total() }} {{ Str::plural('result', $items->total()) }} for “{{ $q }}”</h2>
            <p class="mt-6 text-sm text-muted" role="status">
                {{ $items->total() }} {{ Str::plural('result', $items->total()) }} for “{{ $q }}”
            </p>

            <div class="reveal mt-4 divide-y divide-hairline border border-hairline bg-surface">
                @foreach($items as $item)
                    <article class="px-5 py-4">
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="eyebrow">{{ \App\Support\ContentTypes::typeLabel($item->content_type) }}</span>
                            @if($item->isExternal())
                                <span class="text-divider">·</span>
                                <span class="text-muted">{{ $item->publication?->name ?? 'External' }}</span>
                            @endif
                        </div>
                         <a href="{{ $item->publicPath() }}" class="link-accent mt-1 block font-serif font-bold text-ink">
                            {{ $item->title }}
                        </a>
                        <p class="mt-1 text-sm leading-relaxed text-muted">{{ \Illuminate\Support\Str::limit($item->summary, 180) }}</p>
                        <time class="mt-2 block text-xs text-muted" datetime="{{ ($item->published_at ?? $item->created_at)->toDateString() }}">
                            {{ ($item->published_at ?? $item->created_at)->format('M j, Y') }}
                        </time>
                    </article>
                @endforeach
            </div>

            <div class="mt-8">
                {{ $items->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
