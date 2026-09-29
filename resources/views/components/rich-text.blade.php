{{--
    Render a sanitized rich-text field in the reading column.

    Used by both piece page types so the markup, and the prose styling, live in
    exactly one place. `sections/show` and `work/show` previously had to be kept
    in step by hand, and had already drifted.

    `{!! !!}` is safe here for one reason only: the value passed in was run
    through `HtmlSanitizer::sanitize()` by `ContentItem::saving` before it was
    written. That is the guarantee — not this view. If a rich-text column is
    ever added without a sanitizer, this becomes an XSS hole, so the two are
    changed together or not at all.
--}}
@props([
    'html' => null,
])

@if(filled($html))
    <div class="article-body text-ink">
        {!! $html !!}
    </div>
@endif
