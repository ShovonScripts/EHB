@props(['crumbs' => []])

{{-- SEO.md §7 — Home > [Section] > [Item] --}}
<nav aria-label="Breadcrumb" class="text-xs text-muted">
    <ol class="flex flex-wrap items-center gap-1.5">
        <li><a href="{{ route('home') }}" class="hover:text-accent">Home</a></li>
        @foreach($crumbs as $crumb)
            <li aria-hidden="true" class="text-hairline">›</li>
            <li>
                @if(!empty($crumb['url']))
                    <a href="{{ $crumb['url'] }}" class="hover:text-accent">{{ $crumb['label'] }}</a>
                @else
                    <span class="text-ink">{{ $crumb['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
