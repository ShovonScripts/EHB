<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ContentItem;
use App\Models\JournalistProfile;
use App\Models\Publication;
use App\Models\Topic;
use App\Support\PageCache;
use Illuminate\Support\Carbon;

class HomeController extends Controller
{
    public function __invoke()
    {
        return view('home', PageCache::remember('home', [], fn () => $this->buildData()));
    }

    /**
     * Homepage payload. Cached (ROADMAP Phase 8); any publish/edit/delete
     * bumps the cache version through RecordsActivity, so this stays fresh.
     *
     * @return array<string, mixed>
     */
    private function buildData(): array
    {
        $profile = JournalistProfile::current();
        $profile?->load(['photo', 'publications.logo']);

        $featured = ContentItem::published()
            ->with(['category', 'publication', 'featuredImage'])
            ->where('is_featured', true)
            ->orderByDesc('published_at')
            ->take(5)
            ->get();

        $latest = ContentItem::published()
            ->with(['category', 'publication', 'featuredImage'])
            ->whereNotIn('id', $featured->pluck('id'))
            ->orderByDesc('published_at')
            ->take(6)
            ->get();

        $investigations = ContentItem::published()
            ->with(['category', 'publication', 'featuredImage'])
            ->ofType('investigation')
            ->orderByDesc('published_at')
            ->take(3)
            ->get();

        $publications = Publication::with('logo')
            ->withCount(['contentItems' => fn ($q) => $q->published()])
            ->orderBy('name')
            ->get();

        // The three ways the body of work can be browsed, with counts, so the
        // homepage states the shape of the archive rather than just teasing
        // the latest few pieces.
        $sections = Category::whereHas('contentItems', fn ($q) => $q->published())
            ->withCount(['contentItems' => fn ($q) => $q->published()])
            ->orderByDesc('content_items_count')
            ->orderBy('name')
            ->get();

        $topics = Topic::whereHas('contentItems', fn ($q) => $q->published())
            ->withCount(['contentItems' => fn ($q) => $q->published()])
            ->orderByDesc('content_items_count')
            ->orderBy('name')
            ->get();

        $span = ContentItem::published()
            ->whereNotNull('published_at')
            ->selectRaw('MIN(published_at) as first_at, MAX(published_at) as last_at')
            ->first();

        return [
            'profile' => $profile,
            'featured' => $featured,
            'latest' => $latest,
            'investigations' => $investigations,
            'publications' => $publications,
            'sections' => $sections,
            'topics' => $topics,
            'total' => ContentItem::published()->count(),
            'firstAt' => $span?->first_at ? Carbon::parse($span->first_at) : null,
            'lastAt' => $span?->last_at ? Carbon::parse($span->last_at) : null,
        ];
    }
}
