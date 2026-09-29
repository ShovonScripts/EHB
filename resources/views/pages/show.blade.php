<x-app-layout
    :title="$page->seo_title ?: $page->title"
    :description="$page->seo_description ?: \Illuminate\Support\Str::limit(strip_tags($page->body), 155)"
    :canonical="$page->canonical_url_override ?: url('/'.$page->slug)"
    :og-image="$page->ogImage?->url"
    :breadcrumbs="[['label' => $page->title]]"
>
    <article class="container-reading py-14">
        <p class="eyebrow">Page</p>
        <h1 class="mt-3 text-4xl font-bold text-ink">{{ $page->title }}</h1>

        <div class="article-body mt-8">
            {!! nl2br(e($page->body)) !!}
        </div>
    </article>
</x-app-layout>
