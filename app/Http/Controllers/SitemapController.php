<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ContentItem;
use App\Models\Page;
use App\Models\Publication;
use App\Models\Topic;
use App\Support\ContentTypes;
use Illuminate\Support\Carbon;

/**
 * SEO.md §8 — auto-generated XML sitemap with image extension.
 * Includes static pages, all published content (internal + external
 * summary pages), topic dossiers, and publication pages. Draft and
 * future-scheduled items are excluded by the published() scope.
 */
class SitemapController extends Controller
{
    public function __invoke()
    {
        $lastModified = ContentItem::max('updated_at')
            ? Carbon::parse(ContentItem::max('updated_at'))
            : now();

        $entries = [];

        // Static pages. /search is noindex and /admin is private — excluded.
        $staticPaths = ['/', '/about', '/contact', '/work', '/sections', '/topics', '/publications', '/archive'];

        foreach (array_keys(ContentTypes::SECTIONS) as $key) {
            $staticPaths[] = '/'.$key;
        }

        foreach ($staticPaths as $path) {
            $entries[] = [
                'loc' => url($path),
                'lastmod' => $lastModified,
                'images' => [],
            ];
        }

        // Outlet filing sections, but only those with published pieces — an
        // empty section page has nothing for a crawler to index.
        foreach (Category::whereHas('contentItems', fn ($q) => $q->published())->orderBy('id')->get() as $section) {
            $entries[] = [
                'loc' => route('sections.show', $section->slug),
                'lastmod' => $section->updated_at ?? $lastModified,
                'images' => [],
            ];
        }

        foreach (Topic::whereHas('contentItems', fn ($q) => $q->published())->orderBy('id')->get() as $topic) {
            $entries[] = [
                'loc' => route('topics.show', $topic->slug),
                'lastmod' => $topic->updated_at ?? $lastModified,
                'images' => [],
            ];
        }

        foreach (Publication::orderBy('id')->get() as $publication) {
            $entries[] = [
                'loc' => route('publications.show', $publication->slug),
                'lastmod' => $publication->updated_at ?? $lastModified,
                'images' => [],
            ];
        }

        // Admin-authored CMS pages. The `about` and `contact` slugs are intro
        // blocks for those routes rather than standalone destinations, so they
        // are excluded here — listing them would advertise a second URL for
        // content already canonical at /about and /contact (SEO.md §3).
        foreach (Page::whereNotIn('slug', ['about', 'contact'])->orderBy('id')->get() as $page) {
            $entries[] = [
                'loc' => route('pages.show', $page->slug),
                'lastmod' => $page->updated_at ?? $lastModified,
                'images' => $page->ogImage
                    ? [[
                        'loc' => $page->ogImage->url,
                        'title' => $page->title,
                    ]]
                    : [],
            ];
        }

        foreach (ContentItem::published()->with(['featuredImage', 'ogImage'])->orderBy('id')->get() as $item) {
            // The image extension advertises the same picture the share card
            // shows: dedicated OG image first, featured image as fallback.
            $shareImage = $item->ogImage ?? $item->featuredImage;

            $entries[] = [
                'loc' => url($item->publicPath()),
                'lastmod' => $item->updated_at ?? $lastModified,
                'images' => $shareImage
                    ? [[
                        'loc' => $shareImage->url,
                        'title' => $item->title,
                    ]]
                    : [],
            ];
        }

        return response()
            ->view('sitemap', compact('entries'))
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
