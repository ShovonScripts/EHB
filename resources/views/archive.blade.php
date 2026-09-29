<x-app-layout
    title="Archive"
    description="The complete chronological archive — filter by type, source, category, tag, topic, publication, and year."
    :noindex="$hasFilters"
    :breadcrumbs="[['label' => 'Archive']]"
>
    <div class="container-wide py-14">
        <p class="eyebrow">Everything</p>
        <h1 class="mt-3 text-4xl font-bold text-ink">Full Archive</h1>
        <p class="mt-4 max-w-2xl text-muted">
            Every published piece — internal and external — in one filterable index.
        </p>

        {{-- AdvancedFilterBar — FRONTEND.md §2 /archive --}}
        <form method="GET" action="{{ route('archive') }}" class="reveal mt-8 border border-hairline bg-surface p-5">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <label class="block text-sm">
                    <span class="mb-1 block font-medium text-ink">Type</span>
                    <select name="type" class="w-full border border-hairline bg-paper px-3 py-2 text-sm text-ink focus:border-accent">
                        <option value="">All types</option>
                        @foreach($types as $value => $label)
                            <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block text-sm">
                    <span class="mb-1 block font-medium text-ink">Source</span>
                    <select name="source" class="w-full border border-hairline bg-paper px-3 py-2 text-sm text-ink focus:border-accent">
                        <option value="">All sources</option>
                        <option value="internal" @selected($filters['source'] === 'internal')>On this site</option>
                        <option value="external" @selected($filters['source'] === 'external')>Published elsewhere</option>
                    </select>
                </label>

                <label class="block text-sm">
                    <span class="mb-1 block font-medium text-ink">Category</span>
                    <select name="category" class="w-full border border-hairline bg-paper px-3 py-2 text-sm text-ink focus:border-accent">
                        <option value="">All categories</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->slug }}" @selected($filters['category'] === $cat->slug)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block text-sm">
                    <span class="mb-1 block font-medium text-ink">Year</span>
                    <select name="year" class="w-full border border-hairline bg-paper px-3 py-2 text-sm text-ink focus:border-accent">
                        <option value="">All years</option>
                        @foreach($years as $y)
                            <option value="{{ $y }}" @selected($filters['year'] == $y)>{{ $y }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block text-sm">
                    <span class="mb-1 block font-medium text-ink">Tag</span>
                    <select name="tag" class="w-full border border-hairline bg-paper px-3 py-2 text-sm text-ink focus:border-accent">
                        <option value="">All tags</option>
                        @foreach($tags as $tag)
                            <option value="{{ $tag->slug }}" @selected($filters['tag'] === $tag->slug)>{{ $tag->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block text-sm">
                    <span class="mb-1 block font-medium text-ink">Topic</span>
                    <select name="topic" class="w-full border border-hairline bg-paper px-3 py-2 text-sm text-ink focus:border-accent">
                        <option value="">All topics</option>
                        @foreach($topics as $topic)
                            <option value="{{ $topic->slug }}" @selected($filters['topic'] === $topic->slug)>{{ $topic->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block text-sm">
                    <span class="mb-1 block font-medium text-ink">Publication</span>
                    <select name="publication" class="w-full border border-hairline bg-paper px-3 py-2 text-sm text-ink focus:border-accent">
                        <option value="">All publications</option>
                        @foreach($publications as $pub)
                            <option value="{{ $pub->slug }}" @selected($filters['publication'] === $pub->slug)>{{ $pub->name }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary flex-1 !py-2">Apply Filters</button>
                    @if($hasFilters)
                        <a href="{{ route('archive') }}" class="btn-outline !py-2">Clear</a>
                    @endif
                </div>
            </div>
        </form>

        @if($items->isEmpty())
            <div class="mt-12 border border-dashed border-hairline bg-surface py-16 text-center">
                <p class="text-muted">No pieces match these filters.</p>
                <a href="{{ route('archive') }}" class="link-accent mt-4 inline-block">Clear filters →</a>
            </div>
        @else
            {{-- DenseContentList — FRONTEND.md §2 --}}
            <div class="reveal mt-8 divide-y divide-hairline border border-hairline bg-surface">
                @foreach($items as $item)
                    <x-content-row :item="$item" :show-category="false" />
                @endforeach
            </div>

            <div class="mt-8">
                {{ $items->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
