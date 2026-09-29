@props([
    'url' => null,
    'title' => null,
])

{{--
    Share links (FRONTEND.md §2 — detail pages carry "share links").

    Restrained by DESIGN_SYSTEM.md §7: a small text row, not filled pills.
    The Web Share API is used where the browser has it (native sheet on
    mobile); the network links are the progressive-enhancement fallback, and
    the copy button works everywhere.
--}}
@php
    $shareUrl = $url ?: url()->current();
    $shareTitle = $title ?: (string) ($item->title ?? config('app.name'));
    $encodedUrl = rawurlencode($shareUrl);
    $encodedTitle = rawurlencode($shareTitle);
@endphp

<div class="mt-10 flex flex-wrap items-center gap-x-5 gap-y-3 border-t border-hairline pt-6">
    <p class="eyebrow">Share</p>

    <a href="https://x.com/intent/tweet?url={{ $encodedUrl }}&text={{ $encodedTitle }}"
       target="_blank" rel="noopener noreferrer"
       class="text-sm text-muted transition-colors hover:text-accent">X / Twitter</a>

    <a href="https://www.facebook.com/sharer/sharer.php?u={{ $encodedUrl }}"
       target="_blank" rel="noopener noreferrer"
       class="text-sm text-muted transition-colors hover:text-accent">Facebook</a>

    <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ $encodedUrl }}"
       target="_blank" rel="noopener noreferrer"
       class="text-sm text-muted transition-colors hover:text-accent">LinkedIn</a>

    <button type="button"
            data-share-copy
            data-share-url="{{ $shareUrl }}"
            class="text-sm text-muted transition-colors hover:text-accent">
        Copy link
    </button>

    <span data-share-status role="status" class="sr-only"></span>
</div>
