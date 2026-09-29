{{--
    Editorial pagination (DESIGN_SYSTEM.md §7 — sharp corners, hairline
    borders, no rounded pills so the control matches the rest of the site).

    Overrides the framework's pagination::tailwind view. Accessibility is
    preserved from the stock view: <nav> landmark with an aria-label, a
    `rel="prev"` / `rel="next"` pair (SEO.md §13 — crawlable pagination),
    `aria-current="page"` on the active page, and `aria-hidden` on the
    decorative chevrons.
--}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="pagination">
        <p class="mb-4 text-sm text-muted">
            {!! __('Showing') !!}
            <span class="font-medium text-ink">{{ $paginator->firstItem() }}</span>
            {!! __('to') !!}
            <span class="font-medium text-ink">{{ $paginator->lastItem() }}</span>
            {!! __('of') !!}
            <span class="font-medium text-ink">{{ $paginator->total() }}</span>
            {!! __('results') !!}
        </p>

        <div class="flex items-center gap-2">
            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <span aria-hidden="true"
                      class="inline-flex items-center border border-hairline px-3 py-2 text-sm text-muted opacity-50">&larr;</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}"
                   rel="prev"
                   aria-label="{{ __('pagination.previous') }}"
                   class="inline-flex items-center border border-hairline px-3 py-2 text-sm text-muted transition-colors hover:border-accent hover:text-accent">&larr;</a>
            @endif

            {{-- Page numbers --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span aria-hidden="true" class="px-2 text-sm text-muted">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page"
                                  class="inline-flex items-center border border-accent bg-accent px-3.5 py-2 text-sm font-semibold text-paper">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}"
                               aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                               class="inline-flex items-center border border-hairline px-3.5 py-2 text-sm text-muted transition-colors hover:border-accent hover:text-accent">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}"
                   rel="next"
                   aria-label="{{ __('pagination.next') }}"
                   class="inline-flex items-center border border-hairline px-3 py-2 text-sm text-muted transition-colors hover:border-accent hover:text-accent">&rarr;</a>
            @else
                <span aria-hidden="true"
                      class="inline-flex items-center border border-hairline px-3 py-2 text-sm text-muted opacity-50">&rarr;</span>
            @endif
        </div>
    </nav>
@endif
