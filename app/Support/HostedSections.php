<?php

namespace App\Support;

use App\Models\ContentItem;

/**
 * Which of the content_type sections (/articles, /investigations, /opinions,
 * ...) actually have work hosted on this site.
 *
 * Those pages are internal-only, so while the whole body of work sits at the
 * outlet they are empty. The nav uses this to show them only once there is
 * something to read, rather than carrying four permanent dead links.
 *
 * Version-tagged through PageCache, so publishing the first on-site piece
 * surfaces its section in the nav immediately, with no separate flush to
 * remember and no chance of the two caches disagreeing.
 */
class HostedSections
{
    /**
     * Section definitions (ContentTypes::SECTIONS) that have at least one
     * published internal item, in nav order.
     *
     * @return array<string, array{label: string, types: string[], nav: bool}>
     */
    public static function resolve(): array
    {
        $hostedTypes = PageCache::remember('nav:hosted-sections', [], fn () => ContentItem::published()
            ->internal()
            ->distinct()
            ->pluck('content_type')
            ->all());

        return array_filter(
            ContentTypes::SECTIONS,
            fn (array $section) => in_array($section['types'][0], $hostedTypes, true)
        );
    }
}
