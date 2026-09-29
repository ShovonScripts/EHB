<?php

namespace App\Http\Controllers;

use App\Models\ContentItem;
use App\Models\JournalistProfile;
use App\Support\ContentTypes;
use App\Support\PageCache;
use App\Support\SiteSettings;
use Illuminate\Http\Request;

/**
 * SEO.md §10 — site-wide (/feed) and per-section RSS 2.0 feeds.
 * Items link to their canonical publicPath (internal section URL or
 * /work/{slug} summary page for external pieces) and only ever include
 * published content via the shared published() scope.
 *
 * Cached like the listing pages (ROADMAP Phase 8) — feed readers poll
 * aggressively, so this is the cheapest possible saving.
 */
class FeedController extends Controller
{
    private const ITEM_LIMIT = 20;

    public function site()
    {
        $data = PageCache::remember('feed:site', [], fn () => [
            'items' => $this->query(ContentItem::published())->get(),
            'channel' => [
                // SiteSettings, not config('app.name'): the channel title is
                // editable from Admin → Settings, and this was the last place
                // still reading the env value, so renaming the site there left
                // the feed advertising the old name.
                'title' => SiteSettings::siteName(),
                'link' => url('/'),
                'description' => $this->channelDescription(),
                'selfUrl' => url('/feed'),
            ],
        ]);

        return $this->rssResponse($data['items'], $data['channel']);
    }

    public function section(Request $request)
    {
        $section = (string) $request->route('section');
        $definition = ContentTypes::SECTIONS[$section] ?? abort(404);
        $siteName = SiteSettings::siteName();

        $data = PageCache::remember('feed:section', ['section' => $section], fn () => [
            'items' => $this->query(
                ContentItem::published()->ofTypes($definition['types'])
            )->get(),
            'channel' => [
                'title' => $definition['label'].' — '.$siteName,
                'link' => route('section.'.$section),
                'description' => $definition['label'].' feed from '.$siteName.'.',
                'selfUrl' => route('feed.'.$section),
            ],
        ]);

        return $this->rssResponse($data['items'], $data['channel']);
    }

    private function query($builder)
    {
        return $builder
            ->with(['publication', 'featuredImage'])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(self::ITEM_LIMIT);
    }

    private function channelDescription(): string
    {
        return JournalistProfile::current()?->short_bio
            ?? 'Latest reporting, investigations, interviews, and analysis.';
    }

    private function rssResponse($items, array $channel)
    {
        return response()
            ->view('feeds.rss', ['items' => $items, 'channel' => $channel])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}
